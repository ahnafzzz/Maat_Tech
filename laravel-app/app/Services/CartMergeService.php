<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

class CartMergeService
{
    public function __construct(private readonly SessionCartService $cartService) {}

    public function merge(Request $request, User $user): void
    {
        foreach ($request->session()->get('cart', []) as $productId => $quantity) {
            $this->cartService->addForCustomer($user, Product::findOrFail($productId), $quantity);
        }

        $wishlist = Wishlist::firstOrCreate(['user_id' => $user->id], ['session_id' => null]);

        foreach ($request->session()->get('wishlist', []) as $productId) {
            WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'product_id' => $productId]);
        }

        $request->session()->forget(['cart', 'wishlist']);
    }
}
