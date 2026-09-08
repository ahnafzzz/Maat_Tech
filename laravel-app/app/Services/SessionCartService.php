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

        $product = Product::published()->findOrFail($product->getKey());
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

        $product = Product::published()->findOrFail($product->getKey());
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min($product->stock, $quantity);
        $request->session()->put('cart', $cart);
    }

    public function remove(Request $request, int $productId): void
    {
        if ($customer = $request->user('web')) {
            DB::transaction(function () use ($customer, $productId): void {
                $cartIds = Cart::where('user_id', $customer->id)->orderBy('id')->lockForUpdate()->pluck('id');
                $items = CartItem::whereIn('cart_id', $cartIds)->where('product_id', $productId)
                    ->orderBy('id')->lockForUpdate()->get();
                CartItem::whereIn('id', $items->pluck('id'))->delete();
            }, 3);

            return;
        }

        $cart = $request->session()->get('cart', []);
        unset($cart[$productId], $cart[(string) $productId]);
        $request->session()->put('cart', $cart);
    }

    public function items(Request $request): Collection
    {
        if ($customer = $request->user('web')) {
            return CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $customer->id))
                ->with(['product' => fn ($query) => $query->published()])
                ->get()
                ->groupBy('product_id')
                ->map(function (Collection $items) {
                    $quantity = $items->sum('quantity');
                    $product = $items->first()->product;

                    return [
                        'product_id' => (int) $items->first()->product_id,
                        'product' => $product,
                        'available' => $product !== null,
                        'quantity' => $quantity,
                        'line_total' => $product ? $product->final_price * $quantity : 0,
                    ];
                })->values();
        }

        $cart = $request->session()->get('cart', []);
        $productIds = collect(array_keys($cart))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id);
        $products = Product::published()->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($cart)->map(function ($quantity, int|string $productId) use ($products) {
            if (! ctype_digit((string) $productId) || ! is_int($quantity)) {
                return null;
            }

            $productId = (int) $productId;
            $product = $products->get($productId);

            return [
                'product_id' => $productId,
                'product' => $product,
                'available' => $product !== null,
                'quantity' => $quantity,
                'line_total' => $product ? $product->final_price * $quantity : 0,
            ];
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
            $lockedProduct = Product::published()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $carts = Cart::where('user_id', $customer->id)->orderBy('id')->lockForUpdate()->get();
            if ($carts->isEmpty()) {
                $carts->push(Cart::create(['user_id' => $customer->id, 'session_id' => null]));
            }

            $items = CartItem::whereIn('cart_id', $carts->pluck('id'))
                ->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->get();
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
