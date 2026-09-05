<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\SessionCartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly SessionCartService $cartService) {}

    public function index(Request $request)
    {
        $customer = $request->user('web');
        $cart = Cart::firstOrCreate(['user_id' => $customer->id], ['session_id' => null]);
        $items = CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $customer->id))
            ->with('product')->orderBy('id')->get();

        return response()->json([...$cart->toArray(), 'items' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $customer = $request->user('web');
        $product = Product::findOrFail($data['product_id']);
        $this->cartService->addForCustomer($customer, $product, $data['quantity']);

        return $this->index($request);
    }

    public function destroy(Request $request, string $id)
    {
        CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $request->user('web')->id))
            ->findOrFail($id)->delete();

        return response()->json(['message' => 'Item removed']);
    }
}
