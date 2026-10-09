<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\CheckoutService;
use App\Services\GuestMergeIdentity;
use App\Services\SessionCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly SessionCartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly GuestMergeIdentity $guestMergeIdentity,
    ) {}

    public function cart(Request $request): View
    {
        $items = $this->cartService->items($request);
        $subtotal = $items->sum(fn (array $item) => $item['line_total']);

        return view('cart', [
            'items' => $items,
            'subtotal' => $subtotal,
        ]);
    }

    public function addToCart(Request $request, string $product): RedirectResponse
    {
        $product = Product::published()->findOrFail($product);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'variant_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ]);
        $this->cartService->add($request, $product, $validated['quantity'], $validated['variant_key'] ?? null);

        return back()->with('status', $product->name.' added to cart.');
    }

    public function buyNow(Request $request, string $product): RedirectResponse
    {
        $product = Product::published()->findOrFail($product);
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'variant_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ]);
        $variant = $product->purchasableVariant($validated['variant_key'] ?? null);
        if (! $variant || ! $variant['available'] || $variant['stock'] < $validated['quantity']) {
            throw ValidationException::withMessages(['variant_key' => 'The selected color and quantity are not available.']);
        }

        $activeSelections = collect($request->session()->get('buy_now', []))
            ->filter(fn ($selection) => is_array($selection)
                && isset($selection['created_at'])
                && now()->timestamp - (int) $selection['created_at'] <= 1800)
            ->all();
        $request->session()->put('buy_now', $activeSelections);
        $token = bin2hex(random_bytes(24));
        $request->session()->put('buy_now.'.$token, [
            'product_id' => (int) $product->id,
            'variant_key' => $variant['key'],
            'variant_label' => $variant['label'],
            'quantity' => $validated['quantity'],
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('checkout', ['buy_now' => $token]);
    }

    public function updateCart(Request $request, string $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'variant_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ]);
        $quantity = $validated['quantity'];
        if ($quantity <= 0) {
            $this->cartService->remove($request, (int) $product, $validated['variant_key'] ?? null);
        } else {
            $this->cartService->update($request, Product::published()->findOrFail($product), $quantity, $validated['variant_key'] ?? null);
        }

        return back()->with('status', 'Cart updated.');
    }

    public function removeFromCart(Request $request, string $product): RedirectResponse
    {
        $validated = $request->validate([
            'variant_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ]);
        $this->cartService->remove($request, (int) $product, $validated['variant_key'] ?? null);

        return back()->with('status', 'Item removed from cart.');
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $this->cartService->clear($request);

        return back()->with('status', 'Cart cleared.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $buyNowToken = $request->string('buy_now')->toString();
        $buyNowLines = $buyNowToken !== '' ? $this->buyNowLines($request, $buyNowToken) : null;
        $items = $buyNowLines === null ? $this->cartService->items($request) : $this->cartService->itemsForLines($buyNowLines);

        if ($items->isEmpty()) {
            return back()->with('status', 'Your cart is empty.');
        }

        if ($items->contains(fn (array $item) => ! $item['available'])) {
            return $buyNowLines === null
                ? redirect()->route('cart.index')->withErrors(['cart' => 'Remove unavailable items before checkout.'])
                : back()->withErrors(['cart' => 'The Buy Now selection is no longer available.']);
        }

        $selectedDistrict = $request->user('web')?->district;
        try {
            $quote = $this->checkoutService->preview($items, $selectedDistrict);
        } catch (ValidationException) {
            $selectedDistrict = null;
            $quote = $this->checkoutService->preview($items, null);
        }

        $attemptKey = old('checkout_attempt_key');
        if (! is_string($attemptKey) || ! preg_match(CheckoutService::IDEMPOTENCY_KEY_PATTERN, $attemptKey)) {
            $attemptKey = bin2hex(random_bytes(16));
        }
        if (! $request->user('web')) {
            $this->guestCheckoutIdentity($request);
        }

        return view('checkout', ['items' => $items, ...$quote, 'selectedDistrict' => $selectedDistrict,
            'checkoutAttemptKey' => $attemptKey,
            'buyNowToken' => $buyNowToken !== '' ? $buyNowToken : null,
            'districts' => CheckoutService::DISTRICTS]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'district' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'min:10', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'checkout_attempt_key' => ['required', 'string', 'max:128', 'regex:'.CheckoutService::IDEMPOTENCY_KEY_PATTERN],
            'buy_now_token' => ['nullable', 'string', 'size:48', 'regex:/^[a-f0-9]+$/'],
        ]);

        $validated['district'] = $this->checkoutService->normalizeDistrict($validated['district']);
        $customer = $request->user('web');
        $directLines = ! empty($validated['buy_now_token'])
            ? $this->buyNowLines($request, $validated['buy_now_token'], $validated['checkout_attempt_key'])
            : null;
        $guestCart = $customer ? [] : $request->session()->get('cart', []);
        $guestIdentity = $customer ? null : $this->guestCheckoutIdentity($request);
        $result = $this->checkoutService->checkout(
            $customer,
            $guestCart,
            $validated,
            $validated['checkout_attempt_key'],
            $guestIdentity,
            $directLines,
        );
        $order = $result->order;

        $orderIds = $request->session()->get('order_ids', []);
        $orderIds[] = $order->id;

        $request->session()->put('order_ids', array_values(array_unique($orderIds)));
        if ($directLines === null && ! $customer && ! $result->replayed && $request->session()->get('cart', []) === $guestCart) {
            $request->session()->forget('cart');
        }
        if ($directLines !== null && ! $result->replayed) {
            $request->session()->put(
                'buy_now.'.$validated['buy_now_token'].'.completed_attempt_key',
                $validated['checkout_attempt_key'],
            );
        }

        return redirect()->route('orders.index')->with('status', 'Order placed successfully.');
    }

    private function buyNowLines(Request $request, string $token, ?string $attemptKey = null): array
    {
        $line = $request->session()->get('buy_now.'.$token);
        if (! is_array($line) || ! isset($line['created_at']) || now()->timestamp - (int) $line['created_at'] > 1800) {
            $request->session()->forget('buy_now.'.$token);
            throw ValidationException::withMessages(['cart' => 'This Buy Now selection expired. Return to the product and choose Buy Now again.']);
        }
        $completedAttempt = $line['completed_attempt_key'] ?? null;
        if (is_string($completedAttempt) && ($attemptKey === null || ! hash_equals($completedAttempt, $attemptKey))) {
            throw ValidationException::withMessages(['cart' => 'This Buy Now selection has already been ordered. Start a new Buy Now purchase to order it again.']);
        }

        return [$token => $line];
    }

    private function guestCheckoutIdentity(Request $request): string
    {
        $identity = $request->session()->get('checkout_guest_identity');
        if (! is_string($identity) || ! preg_match('/\A[a-f0-9]{64}\z/', $identity)) {
            $identity = bin2hex(random_bytes(32));
            $request->session()->put('checkout_guest_identity', $identity);
        }

        return $identity;
    }

    public function orders(Request $request): View
    {
        $customer = $request->user('web');
        $orders = Order::with('items.product')
            ->when($customer, fn ($query) => $query->where('user_id', $customer->id), fn ($query) => $query->whereIn('id', $request->session()->get('order_ids', [])))
            ->latest('placed_at')
            ->get();

        return view('orders', ['orders' => $orders]);
    }

    public function wishlist(Request $request): View
    {
        $items = $this->wishlistItems($request);

        return view('wishlist', compact('items'));
    }

    public function toggleWishlist(Request $request, string $product): RedirectResponse
    {
        $productId = (int) $product;

        if ($customer = $request->user('web')) {
            $result = DB::transaction(function () use ($customer, $productId): array {
                $lockedCustomer = $customer->newQuery()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $wishlist = Wishlist::where('user_id', $lockedCustomer->id)->orderBy('id')->lockForUpdate()->first();
                $item = $wishlist ? WishlistItem::where(['wishlist_id' => $wishlist->id, 'product_id' => $productId])->lockForUpdate()->first() : null;

                if ($item) {
                    $item->delete();

                    return ['removed' => true];
                }

                $publishedProduct = Product::published()->whereKey($productId)->lockForUpdate()->firstOrFail();
                $wishlist ??= Wishlist::create(['user_id' => $lockedCustomer->id, 'session_id' => null]);
                WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'product_id' => $publishedProduct->id]);

                return ['removed' => false, 'product' => $publishedProduct];
            }, 3);

            if ($result['removed']) {
                return back()->with('status', 'Item removed from wishlist.');
            }
            $publishedProduct = $result['product'];
        } else {
            $wishlist = $request->session()->get('wishlist', []);
            if (in_array($productId, $wishlist)) {
                $request->session()->put('wishlist', array_values(array_diff($wishlist, [$productId])));
                $this->guestMergeIdentity->markChanged($request);

                return back()->with('status', 'Item removed from wishlist.');
            }

            $publishedProduct = Product::published()->findOrFail($productId);
            $wishlist = [...$wishlist, $publishedProduct->id];
            $request->session()->put('wishlist', $wishlist);
            $this->guestMergeIdentity->markChanged($request);
        }

        return back()->with('status', $publishedProduct->name.' added to wishlist.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'orders' => $user->orders()->with('items')->latest('placed_at')->take(10)->get(),
            'wishlistItems' => $this->wishlistItems($request),
            'cartItems' => $this->cartService->items($request),
        ]);
    }

    private function wishlistItems(Request $request)
    {
        if ($request->user()) {
            return WishlistItem::whereHas('wishlist', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with(['product' => fn ($query) => $query->published()])
                ->get()
                ->map(fn (WishlistItem $item) => [
                    'product_id' => (int) $item->product_id,
                    'product' => $item->product,
                    'available' => $item->product !== null,
                ]);
        }

        $wishlist = collect($request->session()->get('wishlist', []))
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()->values();
        $products = Product::published()->whereIn('id', $wishlist)->get()->keyBy('id');

        return $wishlist->map(fn (int $productId) => [
            'product_id' => $productId,
            'product' => $products->get($productId),
            'available' => $products->has($productId),
        ]);
    }
}
