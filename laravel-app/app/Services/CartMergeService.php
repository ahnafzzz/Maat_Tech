<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Models\User;
use Illuminate\Http\Request;

class CartMergeService
{
    public function merge(Request $request, User $user): void
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id], ['session_id' => null]);

        foreach ($request->session()->get('cart', []) as $productId => $quantity) {
            $item = $cart->items()->firstOrNew(['product_id' => $productId]);
            $item->quantity = ($item->exists ? $item->quantity : 0) + $quantity;
            $item->save();
        }

        $wishlist = Wishlist::firstOrCreate(['user_id' => $user->id], ['session_id' => null]);

        foreach ($request->session()->get('wishlist', []) as $productId) {
            WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'product_id' => $productId]);
        }

        $request->session()->forget(['cart', 'wishlist']);
    }
}
