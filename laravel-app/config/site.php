<?php

return [
    'canonical_host' => env('CANONICAL_HOST', 'www.maattechbd.store'),
    'redirect_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('REDIRECT_HOSTS', 'maattechbd.store')),
    ))),
    'pending_order_expiry_hours' => max(1, (int) env('PENDING_ORDER_EXPIRY_HOURS', 48)),
];
