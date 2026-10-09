<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderLifecycleService
{
    private const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function transition(Order $candidate, string $status, ?string $trackingNumber = null): Order
    {
        return DB::transaction(function () use ($candidate, $status, $trackingNumber): Order {
            $order = Order::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $current = (string) $order->status;
            if ($status !== $current && ! in_array($status, self::TRANSITIONS[$current] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "An order cannot move from {$current} to {$status}.",
                ]);
            }

            if ($status === 'cancelled' && $current !== 'cancelled') {
                $this->restoreReservedStock($order);
                $order->cancelled_at = now();
                $order->expires_at = null;
            }
            if ($status === 'processing' && $current === 'pending') {
                $order->confirmed_at = now();
                $order->expires_at = null;
            }

            $order->status = $status;
            $order->tracking_number = $trackingNumber ?: null;
            $order->save();

            return $order->fresh('items');
        }, 3);
    }

    public function expire(Order $candidate): bool
    {
        return DB::transaction(function () use ($candidate): bool {
            $order = Order::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $order || $order->status !== 'pending' || ! $order->expires_at || $order->expires_at->isFuture()) {
                return false;
            }

            $this->restoreReservedStock($order);
            $order->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'expires_at' => null,
            ])->save();

            return true;
        }, 3);
    }

    private function restoreReservedStock(Order $order): void
    {
        if ($order->stock_released_at) {
            return;
        }

        $items = $order->items()->orderBy('product_id')->lockForUpdate()->get();
        $products = Product::whereIn('id', $items->pluck('product_id')->filter()->unique())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($items->groupBy('product_id') as $productId => $productItems) {
            $product = $products->get($productId);
            if (! $product) {
                throw ValidationException::withMessages(['status' => 'Reserved inventory cannot be restored because a referenced product no longer exists.']);
            }

            $variants = $product->purchasableVariants();
            if ($variants === []) {
                $product->increment('stock', $productItems->sum('quantity'));
                continue;
            }

            foreach ($productItems as $item) {
                $matched = false;
                foreach ($variants as &$variant) {
                    if ($variant['key'] === (string) $item->variant_key) {
                        $variant['stock'] += (int) $item->quantity;
                        $matched = true;
                        break;
                    }
                }
                unset($variant);
                if (! $matched) {
                    throw ValidationException::withMessages(['status' => 'Reserved variant inventory cannot be restored because the product variation changed.']);
                }
            }
            $product->forceFill([
                'variants' => $variants,
                'stock' => collect($variants)->where('available', true)->sum('stock'),
            ])->save();
        }

        $order->stock_released_at = now();
    }
}
