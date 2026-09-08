<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\CheckoutService;
use App\Services\SessionCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly SessionCartService $cartService, private readonly CheckoutService $checkoutService) {}

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
        $this->cartService->add($request, $product, (int) $request->input('quantity', 1));

        return back()->with('status', $product->name.' added to cart.');
    }

    public function updateCart(Request $request, string $product): RedirectResponse
    {
        $quantity = (int) $request->input('quantity', 1);
        if ($quantity <= 0) {
            $this->cartService->remove($request, (int) $product);
        } else {
            $this->cartService->update($request, Product::published()->findOrFail($product), $quantity);
        }

        return back()->with('status', 'Cart updated.');
    }

    public function removeFromCart(Request $request, string $product): RedirectResponse
    {
        $this->cartService->remove($request, (int) $product);

        return back()->with('status', 'Item removed from cart.');
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $this->cartService->clear($request);

        return back()->with('status', 'Cart cleared.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $items = $this->cartService->items($request);

        if ($items->isEmpty()) {
            return back()->with('status', 'Your cart is empty.');
        }

        if ($items->contains(fn (array $item) => ! $item['available'])) {
            return redirect()->route('cart.index')->withErrors(['cart' => 'Remove unavailable items before checkout.']);
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
            'districts' => CheckoutService::DISTRICTS]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'district' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'checkout_attempt_key' => ['required', 'string', 'max:128', 'regex:'.CheckoutService::IDEMPOTENCY_KEY_PATTERN],
        ]);

        $validated['district'] = $this->checkoutService->normalizeDistrict($validated['district']);
        $customer = $request->user('web');
        $guestCart = $customer ? [] : $request->session()->get('cart', []);
        $guestIdentity = $customer ? null : $this->guestCheckoutIdentity($request);
        $result = $this->checkoutService->checkout(
            $customer,
            $guestCart,
            $validated,
            $validated['checkout_attempt_key'],
            $guestIdentity
        );
        $order = $result->order;

        $orderIds = $request->session()->get('order_ids', []);
        $orderIds[] = $order->id;

        $request->session()->put('order_ids', array_values(array_unique($orderIds)));
        if (! $customer && ! $result->replayed && $request->session()->get('cart', []) === $guestCart) {
            $request->session()->forget('cart');
        }

        return redirect()->route('orders.index')->with('status', 'Order placed successfully.');
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
        $orders = Order::with('items')
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

        if ($request->user()) {
            $wishlist = Wishlist::where('user_id', $request->user()->id)->first();
            $item = $wishlist ? WishlistItem::where(['wishlist_id' => $wishlist->id, 'product_id' => $productId])->first() : null;

            if ($item) {
                $item->delete();

                return back()->with('status', 'Item removed from wishlist.');
            }

            $publishedProduct = Product::published()->findOrFail($productId);
            $wishlist ??= Wishlist::firstOrCreate(['user_id' => $request->user()->id], ['session_id' => null]);
            WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'product_id' => $publishedProduct->id]);
        } else {
            $wishlist = $request->session()->get('wishlist', []);
            if (in_array($productId, $wishlist)) {
                $request->session()->put('wishlist', array_values(array_diff($wishlist, [$productId])));

                return back()->with('status', 'Item removed from wishlist.');
            }

            $publishedProduct = Product::published()->findOrFail($productId);
            $wishlist = [...$wishlist, $publishedProduct->id];
            $request->session()->put('wishlist', $wishlist);
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
