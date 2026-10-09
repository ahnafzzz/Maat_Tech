<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductWriteRequest;
use App\Models\Admin;
use App\Models\AdminInvitationRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use App\Notifications\AdminSecurityChangedNotification;
use App\Notifications\AdminTwoFactorCodeNotification;
use App\Services\AdminInvitationService;
use App\Services\AdminSessionVersion;
use App\Services\AdminTwoFactorService;
use App\Services\OrderLifecycleService;
use App\Services\ProductWriteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminSessionVersion $sessionVersion,
        private readonly AdminInvitationService $invitationService,
        private readonly AdminTwoFactorService $twoFactorService,
        private readonly ProductWriteService $productWriteService,
        private readonly OrderLifecycleService $orderLifecycleService,
    ) {}

    public function login(): View
    {
        return view('admin.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'admin_id' => ['required', 'regex:/^ADM-\d{4}-[A-Z]$/'],
            'password' => ['required', 'string'],
        ]);

        $loginKey = $this->loginAttemptKey($request, $validated['admin_id']);
        if (RateLimiter::tooManyAttempts($loginKey, 6)) {
            return back()->withErrors(['admin_id' => 'Too many failed attempts. Try again in '.RateLimiter::availableIn($loginKey).' seconds.'])
                ->onlyInput('admin_id')->setStatusCode(429);
        }

        $admin = Admin::where('admin_id', $validated['admin_id'])->first();

        if (! $admin || ! $admin->isActive() || ! Hash::check($validated['password'], $admin->password)) {
            $this->recordFailedAdminLogin($request, $validated['admin_id'], $loginKey);
            return back()->withErrors(['admin_id' => 'Invalid Admin ID or password.'])->onlyInput('admin_id');
        }

        RateLimiter::clear($loginKey);

        if ($admin->two_factor_enabled) {
            try {
                $created = $this->twoFactorService->createChallenge(
                    $admin,
                    $validated['password'],
                    $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
                    $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
                );
            } catch (Throwable) {
                return back()->withErrors(['admin_id' => 'Unable to start two-factor verification. Try again.'])->onlyInput('admin_id');
            }

            if ($created['status'] === 'rate_limited') {
                return back()->withErrors(['admin_id' => 'Too many verification challenges were requested for this administrator. Try again later.'])->onlyInput('admin_id');
            }

            if ($created['status'] !== 'created') {
                return back()->withErrors(['admin_id' => 'Invalid Admin ID or password.'])->onlyInput('admin_id');
            }

            try {
                $created['admin']->notify(new AdminTwoFactorCodeNotification($created['code']));
                $this->twoFactorService->markDelivered($created['challenge']);
            } catch (Throwable) {
                try {
                    $this->twoFactorService->markDeliveryFailed($created['challenge']);
                } catch (Throwable) {
                    // Without delivered_at, a challenge is unusable even if cleanup fails.
                }

                $this->clearPendingTwoFactor($request);

                return back()->withErrors(['admin_id' => 'Unable to deliver the two-factor code. Try signing in again.'])->onlyInput('admin_id');
            }

            $request->session()->regenerate();
            $request->session()->put([
                AdminTwoFactorService::PENDING_SELECTOR_KEY => $created['challenge']->selector,
                AdminTwoFactorService::PENDING_BINDING_KEY => $created['binding'],
            ]);

            return redirect()->route('admin.two-factor.challenge')->with('status', 'Verification code sent to your admin email.');
        }

        $this->completeAuthentication($request, $admin);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function showTwoFactorChallenge(Request $request): Response|RedirectResponse
    {
        $state = $this->twoFactorService->inspect(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
        );

        if ($state !== 'pending') {
            $this->clearPendingTwoFactor($request);

            return redirect()->route('admin.login')->withErrors(['admin_id' => $this->challengeRecoveryMessage($state)]);
        }

        return response()->view('admin.two-factor')->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $result = $this->twoFactorService->verify(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
            $request->input('code'),
        );

        if ($result['status'] === 'verified') {
            $this->clearPendingTwoFactor($request);
            $this->completeAuthentication($request, $result['admin'], true);

            return redirect()->intended(route('admin.dashboard'))->with('status', 'Two-factor verification complete.');
        }

        if (in_array($result['status'], ['incorrect', 'malformed'], true)) {
            $message = $result['status'] === 'malformed'
                ? 'Enter a six-digit verification code. This attempt was counted.'
                : 'The verification code was not accepted.';

            return back()->withErrors(['code' => $message.' '.$result['remaining_attempts'].' attempts remain.']);
        }

        $this->clearPendingTwoFactor($request);

        return redirect()->route('admin.login')->withErrors(['admin_id' => $this->challengeRecoveryMessage($result['status'])]);
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);
        if (! Hash::check($validated['current_password'], $admin->password)) {
            return back()->withErrors(['current_password' => 'The current administrator password was not accepted.']);
        }

        $enabled = (bool) $validated['enabled'];
        if ($enabled === (bool) $admin->two_factor_enabled) {
            return back()->with('status', 'Two-factor authentication is already '.($enabled ? 'enabled.' : 'disabled.'));
        }

        if ($enabled) {
            $admin = $this->twoFactorService->setEnabled($admin, true);
            try {
                $created = $this->twoFactorService->createChallenge(
                    $admin,
                    $validated['current_password'],
                    $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
                    $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
                );
                if ($created['status'] !== 'created') {
                    throw new \RuntimeException('Enrollment challenge unavailable.');
                }
                $created['admin']->notify(new AdminTwoFactorCodeNotification($created['code']));
                $this->twoFactorService->markDelivered($created['challenge']);
                $request->session()->put([
                    AdminTwoFactorService::PENDING_SELECTOR_KEY => $created['challenge']->selector,
                    AdminTwoFactorService::PENDING_BINDING_KEY => $created['binding'],
                    'admin_two_factor_enrollment_pending' => true,
                ]);
            } catch (Throwable $exception) {
                $this->twoFactorService->setEnabled($admin, false);
                $this->clearPendingTwoFactor($request);
                $request->session()->forget('admin_two_factor_enrollment_pending');
                Log::warning('admin.2fa.enrollment_delivery_failed', ['admin' => hash('sha256', (string) $admin->id)]);

                return back()->withErrors(['current_password' => 'Two-factor enrollment could not be delivered. The setting was not changed.']);
            }

            return redirect()->route('admin.two-factor.enrollment');
        }

        $verifiedAt = (int) $request->session()->get('admin_two_factor_verified_at', 0);
        $maximumAge = (int) config('admin.sensitive_confirmation_minutes', 10) * 60;
        if ($verifiedAt < now()->timestamp - $maximumAge) {
            return back()->withErrors(['current_password' => 'Disabling two-factor authentication requires a fresh second factor. Sign out and sign in again, then retry.']);
        }

        $admin = $this->twoFactorService->setEnabled($admin, false);
        $this->rotateAdminSessions($request, $admin);
        $request->session()->forget(['admin_two_factor_verified_at', 'admin_two_factor_enrollment_pending']);
        $this->notifySecurityChange($admin, 'Two-factor authentication was disabled for your administrator account.');
        Log::notice('admin.2fa.disabled', ['admin' => hash('sha256', (string) $admin->id)]);

        return back()->with('status', 'Administrator two-factor authentication disabled. Other sessions were revoked.');
    }

    public function showTwoFactorEnrollment(Request $request): Response|RedirectResponse
    {
        $state = $this->twoFactorService->inspect(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
        );
        if ($state !== 'pending') {
            $this->clearPendingTwoFactor($request);

            return redirect()->route('admin.dashboard')->withErrors(['current_password' => $this->challengeRecoveryMessage($state)]);
        }

        return response()->view('admin.two-factor', [
            'formAction' => route('admin.two-factor.enrollment.verify'),
            'heading' => 'CONFIRM_2FA_ENROLLMENT',
            'instructions' => 'Enter the code sent to your administrator email to finish enabling two-factor authentication.',
        ])->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function verifyTwoFactorEnrollment(Request $request): RedirectResponse
    {
        $result = $this->twoFactorService->verify(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
            $request->input('code'),
        );
        if ($result['status'] !== 'verified') {
            if (in_array($result['status'], ['incorrect', 'malformed'], true)) {
                return back()->withErrors(['code' => 'The verification code was not accepted. '.$result['remaining_attempts'].' attempts remain.']);
            }

            $this->clearPendingTwoFactor($request);
            return redirect()->route('admin.dashboard')->withErrors(['current_password' => $this->challengeRecoveryMessage($result['status'])]);
        }

        $this->clearPendingTwoFactor($request);
        $request->session()->forget('admin_two_factor_enrollment_pending');
        $request->session()->put('admin_two_factor_verified_at', now()->timestamp);
        $this->rotateAdminSessions($request, $result['admin']);
        $this->notifySecurityChange($result['admin'], 'Two-factor authentication was enabled for your administrator account.');
        Log::notice('admin.2fa.enabled', ['admin' => hash('sha256', (string) $result['admin']->id)]);

        return redirect()->route('admin.dashboard')->with('status', 'Two-factor authentication enabled. Other sessions were revoked.');
    }

    public function dashboard(): View
    {
        $admin = Auth::guard('admin')->user();
        $confirmedStatuses = ['processing', 'shipped', 'delivered'];
        $confirmedOrders = Order::whereIn('status', $confirmedStatuses);
        $statusCounts = Order::query()->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        return view('admin.dashboard', [
            'admin' => $admin,
            'productCount' => Product::count(),
            'activeProductCount' => Product::published()->count(),
            'lowStockProducts' => Product::published()->where('stock', '<=', 5)->orderBy('stock')->orderBy('name')->take(10)->get(),
            'orderCount' => Order::count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'pendingValue' => (float) Order::where('status', 'pending')->sum('total'),
            'confirmedRevenue' => (float) (clone $confirmedOrders)->sum('total'),
            'monthRevenue' => (float) (clone $confirmedOrders)->whereBetween('placed_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'),
            'todayRevenue' => (float) (clone $confirmedOrders)->whereDate('placed_at', today())->sum('total'),
            'averageOrderValue' => (float) ((clone $confirmedOrders)->avg('total') ?? 0),
            'customerCount' => User::count(),
            'statusCounts' => $statusCounts,
            'recentOrders' => Order::with(['items.product', 'user'])->latest('placed_at')->take(10)->get(),
            'admins' => Admin::latest()->get(),
            'requests' => $admin->is_lead ? AdminInvitationRequest::with('requester')
                ->whereIn('status', [
                    AdminInvitationRequest::STATUS_PENDING,
                    AdminInvitationRequest::STATUS_APPROVED,
                    AdminInvitationRequest::STATUS_EXPIRED,
                ])->latest()->get() : collect(),
        ]);
    }

    public function sales(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:pending,processing,shipped,delivered,cancelled,refunded'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = Order::with(['items.product', 'user'])->latest('placed_at')->latest('id');
        if (! empty($validated['q'])) {
            $search = trim($validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_phone', 'like', '%'.$search.'%')
                    ->orWhere('tracking_number', 'like', '%'.$search.'%');
            });
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['from'])) {
            $query->whereDate('placed_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('placed_at', '<=', $validated['to']);
        }

        $filtered = clone $query;
        $confirmed = (clone $filtered)->whereIn('status', ['processing', 'shipped', 'delivered']);

        return view('admin.sales', [
            'orders' => $query->paginate(25)->withQueryString(),
            'filteredOrderCount' => (clone $filtered)->count(),
            'filteredRevenue' => (float) $confirmed->sum('total'),
            'filteredAverage' => (float) ((clone $confirmed)->avg('total') ?? 0),
            'statusCounts' => Order::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'canWrite' => (bool) Auth::guard('admin')->user()?->two_factor_enabled,
        ]);
    }

    public function customers(Request $request): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = User::query()
            ->withCount('orders')
            ->withSum(['orders as lifetime_value' => fn ($orders) => $orders->whereIn('status', ['processing', 'shipped', 'delivered'])], 'total')
            ->withMax('orders', 'placed_at')
            ->latest('id');
        if (! empty($validated['q'])) {
            $search = trim($validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('district', 'like', '%'.$search.'%');
            });
        }

        return view('admin.customers', [
            'customers' => $query->paginate(25)->withQueryString(),
            'registeredCustomers' => User::count(),
            'guestOrders' => Order::whereNull('user_id')->count(),
            'customerRevenue' => (float) Order::whereNotNull('user_id')->whereIn('status', ['processing', 'shipped', 'delivered'])->sum('total'),
        ]);
    }

    public function products(): View
    {
        return view('admin.products', [
            'products' => Product::with('category')->latest()->paginate(20),
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function storeProduct(ProductWriteRequest $request): RedirectResponse
    {
        $this->productWriteService->create($request);

        return back()->with('status', 'Product created.');
    }

    public function updateProduct(ProductWriteRequest $request, Product $product): RedirectResponse
    {
        $result = $this->productWriteService->update($request, $product);

        return back()->with('status', $result['cleanup_failed']
            ? 'Product updated. Some obsolete media could not be cleaned up and should be retried operationally.'
            : 'Product updated.');
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        $result = $this->productWriteService->delete($product);

        return back()->with('status', $result['cleanup_failed']
            ? 'Product deleted. Some unreferenced media could not be cleaned up and should be retried operationally.'
            : 'Product and its media were deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name']]);

        Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
        ]);

        return back()->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name,'.$category->id]]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
        ]);

        return back()->with('status', 'Category renamed.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Move or delete this category\'s products before deleting it.']);
        }

        $category->delete();

        return back()->with('status', 'Empty category deleted.');
    }

    public function updateOrder(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled,refunded'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $this->orderLifecycleService->transition($order, $validated['status'], $validated['tracking_number'] ?? null);
        Log::notice('admin.order.status_changed', [
            'admin' => hash('sha256', (string) Auth::guard('admin')->id()),
            'order' => hash('sha256', (string) $order->id),
            'status' => $validated['status'],
        ]);

        return back()->with('status', 'Order status updated.');
    }

    public function requestInvitation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'proposed_admin_id' => ['required', 'regex:/^ADM-\d{4}-[A-Z]$/'],
        ]);

        $this->invitationService->request(Auth::guard('admin')->user(), $validated);

        return back()->with('status', 'Invitation request sent to the Lead Admin.');
    }

    public function approveInvitation(AdminInvitationRequest $requestItem): RedirectResponse
    {
        $lead = Auth::guard('admin')->user();
        $delivery = $this->invitationService->approve($lead, $requestItem->id);

        return $this->deliverInvitation($delivery['invitation'], $delivery['selector'], $delivery['token'])
            ? back()->with('status', $requestItem->proposed_admin_id.' approved. The acceptance invitation was sent.')
            : back()->withErrors(['invitation' => 'The request was approved, but delivery failed. A lead administrator can resend it.']);
    }

    public function rejectInvitation(Request $request, AdminInvitationRequest $requestItem): RedirectResponse
    {
        $lead = Auth::guard('admin')->user();
        $validated = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);
        $this->invitationService->reject($lead, $requestItem->id, $validated['decision_note'] ?? null);

        return back()->with('status', 'Invitation request rejected.');
    }

    public function resendInvitation(AdminInvitationRequest $requestItem): RedirectResponse
    {
        $delivery = $this->invitationService->resend(Auth::guard('admin')->user(), $requestItem->id);

        return $this->deliverInvitation($delivery['invitation'], $delivery['selector'], $delivery['token'])
            ? back()->with('status', 'A replacement invitation was sent. The previous link is invalid.')
            : back()->withErrors(['invitation' => 'The replacement link was created, but delivery failed. Try resending later.']);
    }

    public function revokeInvitation(AdminInvitationRequest $requestItem): RedirectResponse
    {
        $this->invitationService->revoke(Auth::guard('admin')->user(), $requestItem->id);

        return back()->with('status', 'Invitation revoked.');
    }

    public function showInvitationAcceptance(string $selector): Response
    {
        $invitation = $this->invitationService->findAcceptable($selector);
        abort_unless($invitation, 404);

        $expired = ! $invitation->token_expires_at || now()->greaterThanOrEqualTo($invitation->token_expires_at);

        return $this->invitationAcceptanceResponse($expired ? null : $invitation, $selector, $expired, status: $expired ? 410 : 200);
    }

    public function acceptInvitation(Request $request, string $selector): Response|RedirectResponse
    {
        $validator = Validator::make($request->only(['token', 'password', 'password_confirmation']), [
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $invitation = $this->invitationService->findAcceptable($selector);
        abort_unless($invitation, 404);

        if ($validator->fails()) {
            return $this->invitationAcceptanceResponse(
                $invitation,
                $selector,
                false,
                $validator->errors(),
                $request->string('token')->toString(),
                422,
            );
        }

        $validated = $validator->validated();

        try {
            $admin = $this->invitationService->accept($selector, $validated['token'], $validated['password']);
        } catch (ValidationException $exception) {
            $invitation = $this->invitationService->findAcceptable($selector);

            return $this->invitationAcceptanceResponse(
                $invitation,
                $selector,
                $invitation === null,
                new MessageBag($exception->errors()),
                $invitation ? $validated['token'] : null,
                $invitation ? 422 : 410,
            );
        } catch (Throwable) {
            return $this->invitationAcceptanceResponse(
                $invitation,
                $selector,
                false,
                new MessageBag(['invitation' => 'The administrator account could not be created. The invitation remains available; try again later.']),
                $validated['token'],
                422,
            );
        }

        return redirect()->route('admin.login')->with('status', 'Administrator '.$admin->admin_id.' activated. Sign in to continue.');
    }

    private function invitationAcceptanceResponse(
        ?AdminInvitationRequest $invitation,
        string $selector,
        bool $expired,
        ?MessageBag $errors = null,
        ?string $token = null,
        int $status = 200,
    ): Response {
        return response()->view('admin.accept-invitation', [
            'invitation' => $invitation,
            'selector' => $selector,
            'expired' => $expired,
            'errors' => $errors ?? new MessageBag,
            'token' => $token,
        ], $status)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
        ]);
    }

    private function deliverInvitation(AdminInvitationRequest $invitation, string $selector, string $token): bool
    {
        try {
            Notification::route('mail', $invitation->normalized_email)
                ->notify(new AdminInvitationNotification($invitation, $selector, $token));
            $this->invitationService->markDelivered($invitation->id, $selector, $token);

            return true;
        } catch (Throwable) {
            $this->invitationService->markDeliveryFailed($invitation->id, $selector, $token);

            return false;
        }
    }

    private function completeAuthentication(Request $request, Admin $admin, bool $secondFactorVerified = false): void
    {
        $this->twoFactorService->supersedeBound(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
        );
        $this->clearPendingTwoFactor($request);
        $request->session()->forget('admin_two_factor_enrollment_pending');

        if (! $admin->session_version) {
            $admin->forceFill(['session_version' => Str::random(64)])->save();
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $this->sessionVersion->establish($request, $admin);
        if ($secondFactorVerified) {
            $request->session()->put('admin_two_factor_verified_at', now()->timestamp);
        } else {
            $request->session()->forget('admin_two_factor_verified_at');
        }
        $admin->update(['last_login_at' => now()]);
        Log::info('admin.login.succeeded', ['admin' => hash('sha256', (string) $admin->id)]);
    }

    private function loginAttemptKey(Request $request, string $adminId): string
    {
        return 'admin-login-progressive|'.hash('sha256', strtolower($adminId).'|'.$request->ip());
    }

    private function recordFailedAdminLogin(Request $request, string $adminId, string $key): void
    {
        RateLimiter::hit($key, 60);
        $attempts = RateLimiter::attempts($key);
        usleep(min(800_000, 50_000 * (2 ** min($attempts - 1, 4))));
        Log::warning('admin.login.failed', [
            'identity' => hash('sha256', strtolower($adminId)),
            'source' => hash('sha256', (string) $request->ip()),
            'attempt' => $attempts,
        ]);
    }

    private function rotateAdminSessions(Request $request, Admin $admin): void
    {
        $admin->forceFill(['session_version' => Str::random(64), 'remember_token' => Str::random(60)])->save();
        $this->twoFactorService->invalidatePendingForAdmin($admin->id);
        Auth::guard('admin')->setUser($admin);
        $this->sessionVersion->establish($request, $admin);
    }

    private function notifySecurityChange(Admin $admin, string $message): void
    {
        try {
            $admin->notify(new AdminSecurityChangedNotification($message));
        } catch (Throwable) {
            Log::critical('admin.security_change_notification_failed', ['admin' => hash('sha256', (string) $admin->id)]);
        }
    }

    private function clearPendingTwoFactor(Request $request): void
    {
        $request->session()->forget([
            AdminTwoFactorService::PENDING_SELECTOR_KEY,
            AdminTwoFactorService::PENDING_BINDING_KEY,
            'pending_admin_id',
        ]);
    }

    private function challengeRecoveryMessage(string $state): string
    {
        return match ($state) {
            'expired' => 'The verification challenge expired. Sign in again to request a new code.',
            'exhausted' => 'The verification challenge reached its attempt limit. Sign in again to restart verification.',
            'consumed' => 'That verification challenge was already used. Sign in again if you still need access.',
            'superseded' => 'That verification challenge was replaced by a newer sign-in from this browser.',
            default => 'The verification challenge is no longer available. Sign in again to restart verification.',
        };
    }
}
