<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderLifecycleService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--limit=200 : Maximum expired orders to process}';

    protected $description = 'Cancel expired pending COD orders and idempotently restore reserved stock';

    public function handle(OrderLifecycleService $lifecycle): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $orders = Order::where('status', 'pending')->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->orderBy('id')->limit($limit)->get();
        $expired = 0;
        foreach ($orders as $order) {
            $expired += $lifecycle->expire($order) ? 1 : 0;
        }
        $this->info("Expired {$expired} pending order(s).");

        return self::SUCCESS;
    }
}
