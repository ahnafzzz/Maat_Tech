<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionCartService
{
    public function add(Request $request, Product $product, int $quantity = 1): void
    {
        if ($customer = $request->user('web')) {
            $this->addForCustomer($customer, $product, $quantity);

            return;
        }

        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min($product->stock, ($cart[$product->id] ?? 0) + max(1, $quantity));
        $request->session()->put('cart', $cart);
    }

    public function update(Request $request, Product $product, int $quantity): void
    {
        if ($customer = $request->user('web')) {
            $this->setForCustomer($customer, $product, $quantity);

            return;
        }

        $cart = $request->session()->get('cart', []);

        if ($quantity <= 0) {
            unset($cart[$product->id]);
        } else {
            $cart[$product->id] = min($product->stock, $quantity);
        }

        $request->session()->put('cart', $cart);
    }

    public function items(Request $request): Collection
    {
        if ($customer = $request->user('web')) {
            return CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $customer->id))
                ->with('product')
                ->get()
                ->groupBy('product_id')
                ->map(function (Collection $items) {
                    $quantity = $items->sum('quantity');
                    $product = $items->first()->product;

                    return [
                        'product' => $product,
                        'quantity' => $quantity,
                        'line_total' => $product->final_price * $quantity,
                    ];
                })->values();
        }

        $cart = $request->session()->get('cart', []);
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');

        return collect($cart)->map(function (int $quantity, int|string $productId) use ($products) {
            $product = $products->get((int) $productId);

            return $product ? [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => $product->final_price * $quantity,
            ] : null;
        })->filter()->values();
    }

    public function clear(Request $request): void
    {
        if ($customer = $request->user('web')) {
            DB::transaction(function () use ($customer): void {
                $cartIds = Cart::where('user_id', $customer->id)->orderBy('id')->lockForUpdate()->pluck('id');
                CartItem::whereIn('cart_id', $cartIds)->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();
                CartItem::whereIn('cart_id', $cartIds)->delete();
            });

            return;
        }

        $request->session()->forget('cart');
    }

    public function addForCustomer(User $customer, Product $product, int $quantity): void
    {
        $this->mutateCustomerProduct($customer, $product, fn (int $current, int $stock) => min($stock, $current + max(1, $quantity)));
    }

    private function setForCustomer(User $customer, Product $product, int $quantity): void
    {
        $this->mutateCustomerProduct($customer, $product, fn (int $current, int $stock) => $quantity <= 0 ? 0 : min($stock, $quantity));
    }

    private function mutateCustomerProduct(User $customer, Product $product, callable $quantityResolver): void
    {
        DB::transaction(function () use ($customer, $product, $quantityResolver): void {
            $carts = Cart::where('user_id', $customer->id)->orderBy('id')->lockForUpdate()->get();
            if ($carts->isEmpty()) {
                $carts->push(Cart::create(['user_id' => $customer->id, 'session_id' => null]));
            }

            $items = CartItem::whereIn('cart_id', $carts->pluck('id'))
                ->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->get();
            $lockedProduct = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $quantity = $quantityResolver($items->sum('quantity'), $lockedProduct->stock);

            if ($quantity <= 0) {
                CartItem::whereIn('id', $items->pluck('id'))->delete();

                return;
            }

            $item = $items->shift() ?? new CartItem(['cart_id' => $carts->first()->id, 'product_id' => $product->id]);
            $item->quantity = $quantity;
            $item->save();
            CartItem::whereIn('id', $items->pluck('id'))->delete();
        }, 3);
    }
}
