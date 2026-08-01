<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AdminInvitationRequest;
use App\Models\Category;
use App\Notifications\AdminTwoFactorCodeNotification;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
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
            $plainCode = (string) random_int(100000, 999999);

            $admin->forceFill([
                'two_factor_code' => Hash::make($plainCode),
                'two_factor_expires_at' => now()->addMinutes(10),
            ])->save();

            try {
                $admin->notify(new AdminTwoFactorCodeNotification($plainCode));
            } catch (Throwable) {
                return back()->withErrors(['admin_id' => 'Unable to dispatch the two-factor code. Check mail configuration and try again.'])->onlyInput('admin_id');
            }

            $request->session()->put('pending_admin_id', $admin->id);

            return redirect()->route('admin.two-factor.challenge')->with('status', 'Verification code sent to your admin email.');
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $admin->update(['last_login_at' => now()]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function showTwoFactorChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('pending_admin_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.two-factor');
    }

    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $adminId = $request->session()->get('pending_admin_id');
        $admin = $adminId ? Admin::find($adminId) : null;

        if (! $admin || ! $admin->two_factor_enabled || ! $admin->two_factor_code || ! $admin->two_factor_expires_at || now()->greaterThan($admin->two_factor_expires_at) || ! Hash::check($request->string('code')->toString(), $admin->two_factor_code)) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        $admin->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'last_login_at' => now(),
        ])->save();

        $request->session()->forget('pending_admin_id');
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'))->with('status', 'Two-factor verification complete.');
    }

    public function toggleTwoFactor(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin, 403);

        $admin->forceFill([
            'two_factor_enabled' => ! $admin->two_factor_enabled,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();

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
            'requests' => $admin->is_lead ? AdminInvitationRequest::with('requester')->where('status', 'pending')->latest()->get() : collect(),
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
            'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(5)),
            'sku' => 'ML-' . Str::upper(Str::random(8)),
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
            'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(4)),
        ]);

        return back()->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:categories,name,' . $category->id]]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::lower(Str::random(4)),
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
            'proposed_admin_id' => ['required', 'regex:/^ADM-\d{4}-[A-Z]$/', 'unique:admin_invitation_requests,proposed_admin_id', 'unique:admins,admin_id'],
        ]);

        AdminInvitationRequest::create([
            ...$validated,
            'requested_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('status', 'Invitation request sent to the Lead Admin.');
    }

    public function approveInvitation(AdminInvitationRequest $requestItem): RedirectResponse
    {
        $lead = Auth::guard('admin')->user();
        abort_unless($lead->is_lead, 403);

        $admin = Admin::create([
            'admin_id' => $requestItem->proposed_admin_id,
            'name' => $requestItem->name,
            'email' => $requestItem->email,
            'password' => Hash::make(Str::random(20)),
            'status' => 'active',
        ]);

        $requestItem->update([
            'status' => 'approved',
            'reviewed_by_admin_id' => $lead->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', $admin->admin_id . ' approved. Set a password through the secure invite delivery hook.');
    }

    public function rejectInvitation(Request $request, AdminInvitationRequest $requestItem): RedirectResponse
    {
        $lead = Auth::guard('admin')->user();
        abort_unless($lead->is_lead, 403);

        $requestItem->update([
            'status' => 'rejected',
            'decision_note' => $request->string('decision_note')->toString(),
            'reviewed_by_admin_id' => $lead->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Invitation request rejected.');
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
