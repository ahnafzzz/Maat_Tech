<?php

$proxies = array_map(
    static fn (string $proxy): string => trim($proxy),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
);

return [
    'proxies' => array_values(array_filter($proxies, static fn (string $proxy): bool => $proxy !== '')),
];
