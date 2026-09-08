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
            $product = Product::published()->find($productId);
            if ($product) {
                $this->cartService->addForCustomer($user, $product, $quantity);
            }
        }

        $wishlist = Wishlist::firstOrCreate(['user_id' => $user->id], ['session_id' => null]);

        foreach ($request->session()->get('wishlist', []) as $productId) {
            if (Product::published()->whereKey($productId)->exists()) {
                WishlistItem::firstOrCreate(['wishlist_id' => $wishlist->id, 'product_id' => $productId]);
            }
        }

        $request->session()->forget(['cart', 'wishlist']);
    }
}
