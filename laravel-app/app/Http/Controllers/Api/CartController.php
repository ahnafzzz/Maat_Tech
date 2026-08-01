<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id(), 'session_id' => session()->getId()]);

        return response()->json($cart->load('items.product'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::firstOrCreate(['user_id' => Auth::id(), 'session_id' => session()->getId()]);
        $product = Product::findOrFail($data['product_id']);

        if ($product->stock < $data['quantity']) {
            return response()->json(['message' => 'Not enough stock'], 422);
        }

        $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_id' => $product->id]);
        $item->quantity += $data['quantity'];
        $item->save();

        return response()->json($cart->load('items.product'));
    }

    public function destroy(string $id)
    {
        CartItem::findOrFail($id)->delete();

        return response()->json(['message' => 'Item removed']);
    }
}
