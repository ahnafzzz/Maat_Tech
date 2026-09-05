<?php

namespace App\Services;

use App\Models\Order;

readonly class CheckoutResult
{
    public function __construct(public Order $order, public bool $replayed) {}
}
