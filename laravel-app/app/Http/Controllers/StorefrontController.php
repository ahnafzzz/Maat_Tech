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

    public function addToCart(Request $request, Product $product): RedirectResponse
    {
        $this->cartService->add($request, $product, (int) $request->input('quantity', 1));

        return back()->with('status', $product->name.' added to cart.');
    }

    public function updateCart(Request $request, Product $product): RedirectResponse
    {
        $this->cartService->update($request, $product, (int) $request->input('quantity', 1));

        return back()->with('status', 'Cart updated.');
    }

    public function removeFromCart(Request $request, Product $product): RedirectResponse
    {
        $this->cartService->update($request, $product, 0);

        return back()->with('status', $product->name.' removed from cart.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $items = $this->cartService->items($request);

        if ($items->isEmpty()) {
            return back()->with('status', 'Your cart is empty.');
        }

        $selectedDistrict = $request->user('web')?->district;
        try {
            $quote = $this->checkoutService->preview($items, $selectedDistrict);
        } catch (ValidationException) {
            $selectedDistrict = null;
            $quote = $this->checkoutService->preview($items, null);
        }

        return view('checkout', ['items' => $items, ...$quote, 'selectedDistrict' => $selectedDistrict,
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
        ]);

        $validated['district'] = $this->checkoutService->normalizeDistrict($validated['district']);
        $customer = $request->user('web');
        $order = $this->checkoutService->checkout($customer, $customer ? [] : $request->session()->get('cart', []), $validated);

        $orderIds = $request->session()->get('order_ids', []);
        $orderIds[] = $order->id;

        $request->session()->put('order_ids', array_values(array_unique($orderIds)));
        if (! $customer) {
            $request->session()->forget('cart');
        }

        return redirect()->route('orders.index')->with('status', 'Order placed successfully.');
    }

    public function orders(Request $request): View
    {
        $orders = Order::with('items.product')
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id), fn ($query) => $query->whereIn('id', $request->session()->get('order_ids', [])))
            ->latest('placed_at')
            ->get();

        return view('orders', ['orders' => $orders]);
    }

    public function wishlist(Request $request): View
    {
        $items = $this->wishlistItems($request);

        return view('wishlist', compact('items'));
    }

    public function toggleWishlist(Request $request, Product $product): RedirectResponse
    {
        if ($request->user()) {
            $wishlist = Wishlist::firstOrCreate(['user_id' => $request->user()->id], ['session_id' => null]);
            $item = WishlistItem::where(['wishlist_id' => $wishlist->id, 'product_id' => $product->id])->first();

            $item ? $item->delete() : WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $product->id]);
        } else {
            $wishlist = $request->session()->get('wishlist', []);
            $wishlist = in_array($product->id, $wishlist) ? array_values(array_diff($wishlist, [$product->id])) : [...$wishlist, $product->id];
            $request->session()->put('wishlist', $wishlist);
        }

        return back()->with('status', $product->name.' wishlist updated.');
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'orders' => $user->orders()->with('items.product')->latest('placed_at')->take(10)->get(),
            'wishlistItems' => $this->wishlistItems($request),
            'cartItems' => $this->cartService->items($request),
        ]);
    }

    private function wishlistItems(Request $request)
    {
        if ($request->user()) {
            return Wishlist::where('user_id', $request->user()->id)->with('items.product')->first()?->items ?? collect();
        }

        return Product::whereIn('id', $request->session()->get('wishlist', []))->get();
    }
}
