<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SessionCartService
{
    public function add(Request $request, Product $product, int $quantity = 1): void
    {
        if ($request->user()) {
            $cart = Cart::firstOrCreate(['user_id' => $request->user()->id], ['session_id' => null]);
            $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
            $item->quantity = min($product->stock, ($item->exists ? $item->quantity : 0) + max(1, $quantity));
            $item->save();

            return;
        }

        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min($product->stock, ($cart[$product->id] ?? 0) + max(1, $quantity));
        $request->session()->put('cart', $cart);
    }

    public function update(Request $request, Product $product, int $quantity): void
    {
        if ($request->user()) {
            $cart = Cart::where('user_id', $request->user()->id)->first();
            $item = $cart?->items()->where('product_id', $product->id)->first();

            if (! $item) {
                return;
            }

            if ($quantity <= 0) {
                $item->delete();
            } else {
                $item->update(['quantity' => min($product->stock, $quantity)]);
            }

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
        if ($request->user()) {
            return Cart::where('user_id', $request->user()->id)
                ->with('items.product')
                ->first()?->items
                ->map(fn ($item) => [
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'line_total' => $item->product->final_price * $item->quantity,
                ]) ?? collect();
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
        if ($request->user()) {
            Cart::where('user_id', $request->user()->id)->first()?->items()->delete();

            return;
        }

        $request->session()->forget('cart');
    }
}
