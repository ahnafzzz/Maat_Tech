<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\SessionCartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly SessionCartService $cartService)
    {
    }

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

        return back()->with('status', $product->name . ' added to cart.');
    }

    public function updateCart(Request $request, Product $product): RedirectResponse
    {
        $this->cartService->update($request, $product, (int) $request->input('quantity', 1));

        return back()->with('status', 'Cart updated.');
    }

    public function removeFromCart(Request $request, Product $product): RedirectResponse
    {
        $this->cartService->update($request, $product, 0);

        return back()->with('status', $product->name . ' removed from cart.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $items = $this->cartService->items($request);

        if ($items->isEmpty()) {
            return back()->with('status', 'Your cart is empty.');
        }

        $subtotal = $items->sum(fn (array $item) => $item['line_total']);
        $shippingFee = $this->shippingFeeForDistrict((string) ($request->user()?->district ?? ''));

        return view('checkout', compact('items', 'subtotal', 'shippingFee'));
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

        $items = $this->cartService->items($request);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $subtotal = $items->sum(fn (array $item) => $item['line_total']);
        $shippingFee = $this->shippingFeeForDistrict($validated['district']);

        $order = Order::create([
            'user_id' => $request->user()?->id,
            'order_number' => 'MECH-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_method' => 'pathao',
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total' => $subtotal + $shippingFee,
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'district' => $validated['district'],
            'address' => $validated['address'],
            'customer_note' => $validated['customer_note'] ?? null,
            'shipping_address' => [
                'district' => $validated['district'],
                'address' => $validated['address'],
            ],
            'placed_at' => now(),
        ]);

        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'unit_price' => $item['product']->final_price,
            ]);
        }

        $orderIds = $request->session()->get('order_ids', []);
        $orderIds[] = $order->id;

        $request->session()->put('order_ids', array_values(array_unique($orderIds)));
        $this->cartService->clear($request);

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

        return back()->with('status', $product->name . ' wishlist updated.');
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

    private function shippingFeeForDistrict(string $district): int
    {
        $normalized = Str::lower(trim($district));

        if ($normalized === 'dhaka') {
            return 80;
        }

        if ($normalized === '') {
            return 120;
        }

        return 140;
    }

    private function wishlistItems(Request $request)
    {
        if ($request->user()) {
            return Wishlist::where('user_id', $request->user()->id)->with('items.product')->first()?->items ?? collect();
        }

        return Product::whereIn('id', $request->session()->get('wishlist', []))->get();
    }
}
