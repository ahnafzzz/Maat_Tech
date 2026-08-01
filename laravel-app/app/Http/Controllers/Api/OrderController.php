<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        return Order::where('user_id', Auth::id())->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_method' => 'required|string',
            'shipping_method' => 'required|string',
            'shipping_address' => 'required|array',
        ]);

        $cart = Cart::where('user_id', Auth::id())->with('items.product')->latest()->first();
        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $subtotal = $cart->items->sum(fn ($item) => $item->product->price * $item->quantity);
        $shippingFee = $data['shipping_address']['city'] === 'Dhaka' ? 80 : 130;
        $total = $subtotal + $shippingFee;

        $order = Order::create([
            'user_id' => Auth::id(),
            'order_number' => 'MECH-' . now()->format('YmdHis'),
            'status' => 'pending',
            'payment_method' => $data['payment_method'],
            'shipping_method' => $data['shipping_method'],
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total' => $total,
            'shipping_address' => $data['shipping_address'],
        ]);

        foreach ($cart->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->product->price,
            ]);

            $item->product->decrement('stock', $item->quantity);
        }

        $cart->items()->delete();

        return response()->json($order->load('items.product'), 201);
    }
}
