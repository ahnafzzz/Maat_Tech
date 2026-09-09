<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminInvitationRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\AdminInvitationNotification;
use App\Notifications\AdminTwoFactorCodeNotification;
use App\Services\AdminInvitationService;
use App\Services\AdminSessionVersion;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

        $admin = Admin::where('admin_id', $validated['admin_id'])->first();

        if (! $admin || ! $admin->isActive() || ! Hash::check($validated['password'], $admin->password)) {
            return back()->withErrors(['admin_id' => 'Invalid Admin ID or password.'])->onlyInput('admin_id');
        }

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
            $this->completeAuthentication($request, $result['admin']);

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

        $admin = $this->twoFactorService->toggle($admin);

        return back()->with('status', $admin->two_factor_enabled ? 'Admin two-factor authentication enabled.' : 'Admin two-factor authentication disabled.');
    }

    public function dashboard(): View
    {
        $admin = Auth::guard('admin')->user();

        return view('admin.dashboard', [
            'admin' => $admin,
            'productCount' => Product::count(),
            'orderCount' => Order::count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'recentOrders' => Order::with('items.product')->latest('placed_at')->take(8)->get(),
            'admins' => Admin::latest()->get(),
            'requests' => $admin->is_lead ? AdminInvitationRequest::with('requester')
                ->whereIn('status', [
                    AdminInvitationRequest::STATUS_PENDING,
                    AdminInvitationRequest::STATUS_APPROVED,
                    AdminInvitationRequest::STATUS_EXPIRED,
                ])->latest()->get() : collect(),
        ]);
    }

    public function products(): View
    {
        return view('admin.products', [
            'products' => Product::with('category')->latest()->paginate(20),
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $this->productValidation($request);

        $product = Product::create([
            ...collect($validated)->except(['images', 'video'])->all(),
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
            'sku' => 'ML-'.Str::upper(Str::random(8)),
            'specs' => [],
            'images' => [],
        ]);
        $this->syncProductMedia($request, $product);

        return back()->with('status', 'Product created.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->productValidation($request);
        $remainingImages = collect($product->images ?? [])->diff($request->input('remove_images', []))->count();

        if ($remainingImages + count($request->file('images', [])) > 10) {
            throw ValidationException::withMessages(['images' => 'A product can have a maximum of 10 photos. Remove existing photos before uploading more.']);
        }

        $product->update(collect($validated)->except(['images', 'video'])->all());
        $this->syncProductMedia($request, $product);

        return back()->with('status', 'Product updated.');
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        $this->deleteProductMedia($product);
        $product->delete();

        return back()->with('status', 'Product and its media were deleted.');
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

        $order->update($validated);

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

    private function productValidation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'gte:price'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:active,draft,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string'],
            'remove_video' => ['nullable', 'boolean'],
        ]);
    }

    private function completeAuthentication(Request $request, Admin $admin): void
    {
        $this->twoFactorService->supersedeBound(
            $request->session()->get(AdminTwoFactorService::PENDING_SELECTOR_KEY),
            $request->session()->get(AdminTwoFactorService::PENDING_BINDING_KEY),
        );
        $this->clearPendingTwoFactor($request);

        if (! $admin->session_version) {
            $admin->forceFill(['session_version' => Str::random(64)])->save();
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $this->sessionVersion->establish($request, $admin);
        $admin->update(['last_login_at' => now()]);
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

    private function syncProductMedia(Request $request, Product $product): void
    {
        $existingImages = collect($product->images ?? []);
        $requestedRemovals = collect($request->input('remove_images', []));
        $removedImages = $existingImages->intersect($requestedRemovals);

        Storage::disk('public')->delete($removedImages->all());
        $images = $existingImages->diff($removedImages)->values();

        foreach ($request->file('images', []) as $image) {
            $images->push($image->store("products/{$product->id}/images", 'public'));
        }

        $videoPath = $product->video_path;
        if ($request->boolean('remove_video') && $videoPath) {
            Storage::disk('public')->delete($videoPath);
            $videoPath = null;
        }

        if ($request->hasFile('video')) {
            if ($videoPath) {
                Storage::disk('public')->delete($videoPath);
            }

            $videoPath = $request->file('video')->store("products/{$product->id}/video", 'public');
        }

        $product->update([
            'images' => $images->all(),
            'image' => $images->first(),
            'video_path' => $videoPath,
        ]);
    }

    private function deleteProductMedia(Product $product): void
    {
        Storage::disk('public')->delete(array_filter([
            ...($product->images ?? []),
            $product->video_path,
        ]));
    }
}
