<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Services\SessionCartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly SessionCartService $cartService) {}

    public function index(Request $request)
    {
        $customer = $request->user('web');
        $cart = Cart::where('user_id', $customer->id)->with([
            'items' => fn ($query) => $query->with(['product' => fn ($productQuery) => $productQuery->published()])->orderBy('id'),
        ])->first();
        $items = collect($cart?->items)->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'available' => $item->product !== null,
            'product' => $item->product,
        ]);

        return response()->json([
            'id' => $cart?->id,
            'user_id' => $customer->id,
            'session_id' => null,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $customer = $request->user('web');
        $product = Product::published()->findOrFail($data['product_id']);
        $this->cartService->addForCustomer($customer, $product, $data['quantity']);

        return $this->index($request);
    }

    public function destroy(Request $request, string $id)
    {
        $this->cartService->removeItemForCustomer($request->user('web'), (int) $id);

        return response()->json(['message' => 'Item removed']);
    }
}
