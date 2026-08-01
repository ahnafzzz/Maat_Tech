<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserOwnsOrder
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = Order::find($request->route('order'));

        if (! $order || $order->user_id !== $request->user()?->id) {
            abort(403, 'You do not have access to this order.');
        }

        return $next($request);
    }
}
