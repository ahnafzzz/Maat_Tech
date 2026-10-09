<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public const IDEMPOTENCY_KEY_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._:-]{15,127}\z/';

    public const DISTRICTS = [
        'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur',
        'Chapainawabganj', 'Chattogram', 'Chuadanga', "Cox's Bazar", 'Cumilla', 'Dhaka', 'Dinajpur',
        'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore',
        'Jhalokathi', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna', 'Kishoreganj', 'Kurigram',
        'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur', 'Magura', 'Manikganj', 'Meherpur',
        'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi',
        'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh', 'Patuakhali',
        'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur',
        'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
    ];

    public function normalizeDistrict(string $district, string $field = 'district'): string
    {
        $normalized = Str::lower(trim($district));
        $match = collect(self::DISTRICTS)->first(fn (string $supported) => Str::lower($supported) === $normalized);

        if (! $match) {
            throw ValidationException::withMessages([$field => 'Select a supported Bangladesh district.']);
        }

        return $match;
    }

    public function shippingFeeMinor(string $district): int
    {
        $this->normalizeDistrict($district);

        return 0;
    }

    public function preview(Collection $items, ?string $district): array
    {
        $subtotalMinor = $items->sum(fn (array $item) => $this->effectivePriceMinor($item['product']) * $item['quantity']);
        $shippingMinor = $district ? $this->shippingFeeMinor($district) : 0;

        return [
            'subtotal' => $this->minorToDecimal($subtotalMinor),
            'shippingFee' => $this->minorToDecimal($shippingMinor),
            'total' => $this->minorToDecimal($subtotalMinor + $shippingMinor),
        ];
    }

    public function checkout(
        ?User $customer,
        array $guestCart,
        array $details,
        string $attemptKey,
        ?string $guestIdentity = null,
        ?array $directLines = null,
    ): CheckoutResult {
        $details = $this->normalizedDetails($details);
        [$ownerType, $ownerIdentifier] = $this->attemptOwner($customer, $guestIdentity);
        $normalizedDirectLines = $directLines === null ? null : $this->validatedGuestCart($directLines);
        $fingerprint = hash('sha256', json_encode([
            'details' => $details,
            'direct_lines' => $normalizedDirectLines,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $customer,
                $guestCart,
                $details,
                $attemptKey,
                $ownerType,
                $ownerIdentifier,
                $fingerprint,
                $normalizedDirectLines,
            ): CheckoutResult {
                if ($customer) {
                    User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                }

                $existing = CheckoutAttempt::where([
                    'owner_type' => $ownerType,
                    'owner_identifier' => $ownerIdentifier,
                    'attempt_key' => $attemptKey,
                ])->lockForUpdate()->first();

                if ($existing) {
                    return $this->replay($existing, $fingerprint);
                }

                $attempt = CheckoutAttempt::create([
                    'owner_type' => $ownerType,
                    'owner_identifier' => $ownerIdentifier,
                    'attempt_key' => $attemptKey,
                    'fingerprint' => $fingerprint,
                ]);

                [$lines, $cartItemIds] = $normalizedDirectLines !== null
                    ? [$normalizedDirectLines, []]
                    : ($customer ? $this->lockedCustomerCart($customer) : [$this->validatedGuestCart($guestCart), []]);

                if ($lines === []) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                $productIds = array_values(array_unique(array_column($lines, 'product_id')));
                sort($productIds, SORT_NUMERIC);
                $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                if ($products->count() !== count($productIds)) {
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                }

                $subtotalMinor = 0;
                $snapshot = [];
                foreach ($lines as $line) {
                    $productId = $line['product_id'];
                    $product = $products->get($productId);
                    $quantity = $line['quantity'];
                    if ($product->status !== 'active') {
                        throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                    }
                    $variant = $product->purchasableVariant($line['variant_key']);
                    if (! $variant || ! $variant['available']) {
                        throw ValidationException::withMessages(['cart' => "The selected color for $product->name is no longer available."]);
                    }
                    if ($variant['stock'] < $quantity) {
                        throw ValidationException::withMessages(['cart' => "Insufficient stock for $product->name{$this->variantSuffix($variant['label'])}."]);
                    }
                    $unitPriceMinor = $this->effectivePriceMinor($product);
                    $subtotalMinor += $unitPriceMinor * $quantity;
                    $snapshot[] = [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'product_sku' => $product->sku ?: 'SKU unavailable',
                        'variant_key' => $variant['key'] ?: null,
                        'variant_label' => $variant['label'],
                        'quantity' => $quantity,
                        'unit_price' => $this->minorToDecimal($unitPriceMinor),
                    ];
                }

                $shippingMinor = $this->shippingFeeMinor($details['district']);
                $order = Order::create([
                    'user_id' => $customer?->id,
                    'order_number' => 'MECH-'.now()->format('YmdHis').'-'.Str::upper(Str::random(12)),
                    'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'pending',
                    'shipping_method' => 'pathao', 'subtotal' => $this->minorToDecimal($subtotalMinor),
                    'shipping_fee' => $this->minorToDecimal($shippingMinor),
                    'total' => $this->minorToDecimal($subtotalMinor + $shippingMinor),
                    'customer_name' => $details['name'], 'customer_phone' => $details['phone'],
                    'district' => $details['district'], 'address' => $details['address'],
                    'customer_note' => $details['customer_note'],
                    'shipping_address' => ['name' => $details['name'], 'phone' => $details['phone'],
                        'district' => $details['district'], 'city' => $details['district'], 'address' => $details['address']],
                    'placed_at' => now(),
                    'expires_at' => now()->addHours((int) config('site.pending_order_expiry_hours', 48)),
                ]);

                foreach ($snapshot as $item) {
                    $order->items()->create($item);
                }

                foreach (collect($snapshot)->groupBy('product_id') as $productId => $productLines) {
                    $product = $products->get($productId);
                    $variants = $product->purchasableVariants();
                    if ($variants === []) {
                        $quantity = $productLines->sum('quantity');
                        $updated = Product::whereKey($productId)->where('stock', '>=', $quantity)
                            ->update(['stock' => DB::raw('stock - '.(int) $quantity)]);
                        if ($updated !== 1) {
                            throw ValidationException::withMessages(['cart' => "Insufficient stock for $product->name."]);
                        }
                        continue;
                    }

                    foreach ($productLines as $item) {
                        foreach ($variants as &$variant) {
                            if ($variant['key'] === $item['variant_key']) {
                                if (! $variant['available'] || $variant['stock'] < $item['quantity']) {
                                    throw ValidationException::withMessages(['cart' => "Insufficient stock for $product->name{$this->variantSuffix($variant['label'])}."]);
                                }
                                $variant['stock'] -= $item['quantity'];
                                break;
                            }
                        }
                        unset($variant);
                    }
                    $product->forceFill([
                        'variants' => $variants,
                        'stock' => collect($variants)->where('available', true)->sum('stock'),
                    ])->save();
                }

                if ($cartItemIds !== []) {
                    CartItem::whereIn('id', $cartItemIds)->delete();
                }

                $attempt->update([
                    'cart_snapshot' => $snapshot,
                    'order_id' => $order->id,
                    'completed_at' => now(),
                ]);

                return new CheckoutResult($order->load('items.product'), false);
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isAttemptUniquenessViolation($exception)) {
                throw $exception;
            }

            return DB::transaction(function () use ($ownerType, $ownerIdentifier, $attemptKey, $fingerprint): CheckoutResult {
                $attempt = CheckoutAttempt::where([
                    'owner_type' => $ownerType,
                    'owner_identifier' => $ownerIdentifier,
                    'attempt_key' => $attemptKey,
                ])->lockForUpdate()->first();

                if (! $attempt) {
                    throw new \RuntimeException('The checkout attempt could not be resolved after contention.');
                }

                return $this->replay($attempt, $fingerprint);
            }, 3);
        }
    }

    private function normalizedDetails(array $details): array
    {
        $phone = trim((string) $details['phone']);
        if (preg_match('/^(?:\+?880|0)1[3-9]\d{8}$/D', $phone) !== 1) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Bangladesh mobile number.']);
        }
        $address = trim((string) $details['address']);
        if (mb_strlen($address) < 10) {
            throw ValidationException::withMessages(['address' => 'Enter a complete delivery address.']);
        }

        return [
            'name' => trim((string) $details['name']),
            'phone' => $phone,
            'address' => $address,
            'district' => $this->normalizeDistrict((string) $details['district']),
            'customer_note' => isset($details['customer_note']) && trim((string) $details['customer_note']) !== ''
                ? trim((string) $details['customer_note'])
                : null,
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
        ];
    }

    private function attemptOwner(?User $customer, ?string $guestIdentity): array
    {
        if ($customer) {
            return ['customer', (string) $customer->getKey()];
        }

        if (! is_string($guestIdentity) || $guestIdentity === '') {
            throw new \InvalidArgumentException('A server-controlled guest checkout identity is required.');
        }

        return ['guest', hash('sha256', $guestIdentity)];
    }

    private function replay(CheckoutAttempt $attempt, string $fingerprint): CheckoutResult
    {
        if (! hash_equals($attempt->fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'checkout_attempt_key' => 'This checkout key was already used with different checkout details.',
            ])->status(409);
        }

        if (! $attempt->order_id || ! $attempt->completed_at) {
            throw new \RuntimeException('The checkout attempt is not complete.');
        }

        return new CheckoutResult($attempt->order()->with('items')->firstOrFail(), true);
    }

    private function isAttemptUniquenessViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'checkout_attempt');
    }

    private function lockedCustomerCart(User $customer): array
    {
        $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
        if (! $cart) {
            return [[], []];
        }
        $items = CartItem::where('cart_id', $cart->id)->orderBy('product_id')->orderBy('variant_key')->lockForUpdate()->get();
        $lines = [];
        foreach ($items as $item) {
            if (! is_int($item->quantity) || $item->quantity <= 0) {
                throw ValidationException::withMessages(['cart' => 'Cart quantities must be positive whole numbers.']);
            }
            $lines[] = [
                'product_id' => (int) $item->product_id,
                'variant_key' => (string) $item->variant_key,
                'variant_label' => $item->variant_label,
                'quantity' => $item->quantity,
            ];
        }

        return [$lines, $items->pluck('id')->all()];
    }

    private function validatedGuestCart(array $cart): array
    {
        $lines = [];
        foreach ($cart as $key => $entry) {
            if (ctype_digit((string) $key) && is_int($entry) && $entry > 0) {
                $lines[] = ['product_id' => (int) $key, 'variant_key' => '', 'variant_label' => null, 'quantity' => $entry];
                continue;
            }
            if (! is_array($entry) || ! isset($entry['product_id'], $entry['quantity'])
                || ! ctype_digit((string) $entry['product_id']) || ! is_int($entry['quantity']) || $entry['quantity'] <= 0) {
                throw ValidationException::withMessages(['cart' => 'Cart quantities must be positive whole numbers.']);
            }
            $variantKey = (string) ($entry['variant_key'] ?? '');
            if ($variantKey !== '' && preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $variantKey) !== 1) {
                throw ValidationException::withMessages(['cart' => 'The cart contains an invalid product variation.']);
            }
            $lines[] = [
                'product_id' => (int) $entry['product_id'],
                'variant_key' => $variantKey,
                'variant_label' => isset($entry['variant_label']) ? (string) $entry['variant_label'] : null,
                'quantity' => $entry['quantity'],
            ];
        }

        return collect($lines)->groupBy(fn (array $line) => $line['product_id'].'|'.$line['variant_key'])
            ->map(function (Collection $group): array {
                $line = $group->first();
                $line['quantity'] = $group->sum('quantity');

                return $line;
            })->values()->all();
    }

    private function effectivePriceMinor(Product $product): int
    {
        return $product->finalPriceMinor();
    }

    private function decimalToMinor(string $amount): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new \UnexpectedValueException('Invalid stored monetary value.');
        }
        $minor = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$minor : $minor;
    }

    private function minorToDecimal(int $minor): string
    {
        return sprintf('%d.%02d', intdiv($minor, 100), abs($minor % 100));
    }

    private function variantSuffix(?string $label): string
    {
        return $label ? ' ('.$label.')' : '';
    }
}
