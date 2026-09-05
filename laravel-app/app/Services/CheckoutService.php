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
        return $this->normalizeDistrict($district) === 'Dhaka' ? 8000 : 14000;
    }

    public function preview(Collection $items, ?string $district): array
    {
        $subtotalMinor = $items->sum(fn (array $item) => $this->effectivePriceMinor($item['product']) * $item['quantity']);
        $shippingMinor = $district ? $this->shippingFeeMinor($district) : null;

        return [
            'subtotal' => $this->minorToDecimal($subtotalMinor),
            'shippingFee' => $shippingMinor === null ? null : $this->minorToDecimal($shippingMinor),
            'total' => $shippingMinor === null ? null : $this->minorToDecimal($subtotalMinor + $shippingMinor),
        ];
    }

    public function checkout(
        ?User $customer,
        array $guestCart,
        array $details,
        string $attemptKey,
        ?string $guestIdentity = null
    ): CheckoutResult {
        $details = $this->normalizedDetails($details);
        [$ownerType, $ownerIdentifier] = $this->attemptOwner($customer, $guestIdentity);
        $fingerprint = hash('sha256', json_encode($details, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use (
                $customer,
                $guestCart,
                $details,
                $attemptKey,
                $ownerType,
                $ownerIdentifier,
                $fingerprint
            ): CheckoutResult {
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

                [$quantities, $cartItemIds] = $customer
                    ? $this->lockedCustomerCart($customer)
                    : [$this->validatedGuestCart($guestCart), []];

                if ($quantities === []) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                $productIds = array_keys($quantities);
                sort($productIds, SORT_NUMERIC);
                $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                if ($products->count() !== count($productIds)) {
                    throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                }

                $subtotalMinor = 0;
                $snapshot = [];
                foreach ($productIds as $productId) {
                    $product = $products->get($productId);
                    $quantity = $quantities[$productId];
                    if ($product->status !== 'active') {
                        throw ValidationException::withMessages(['cart' => "$product->name is not currently available."]);
                    }
                    if ($product->stock < $quantity) {
                        throw ValidationException::withMessages(['cart' => "Insufficient stock for $product->name."]);
                    }
                    $unitPriceMinor = $this->effectivePriceMinor($product);
                    $subtotalMinor += $unitPriceMinor * $quantity;
                    $snapshot[] = [
                        'product_id' => $productId,
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
                ]);

                foreach ($snapshot as $item) {
                    $product = $products->get($item['product_id']);
                    $order->items()->create($item);
                    $updated = Product::whereKey($item['product_id'])->where('stock', '>=', $item['quantity'])
                        ->update(['stock' => DB::raw('stock - '.(int) $item['quantity'])]);
                    if ($updated !== 1) {
                        throw ValidationException::withMessages(['cart' => "Insufficient stock for $product->name."]);
                    }
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
        return [
            'name' => trim((string) $details['name']),
            'phone' => trim((string) $details['phone']),
            'address' => trim((string) $details['address']),
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

        return new CheckoutResult($attempt->order()->with('items.product')->firstOrFail(), true);
    }

    private function isAttemptUniquenessViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'checkout_attempt');
    }

    private function lockedCustomerCart(User $customer): array
    {
        $carts = Cart::where('user_id', $customer->id)->orderBy('id')->lockForUpdate()->get();
        if ($carts->isEmpty()) {
            return [[], []];
        }
        $items = CartItem::whereIn('cart_id', $carts->pluck('id'))->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();
        $quantities = [];
        foreach ($items as $item) {
            if (! is_int($item->quantity) || $item->quantity <= 0) {
                throw ValidationException::withMessages(['cart' => 'Cart quantities must be positive whole numbers.']);
            }
            $quantities[$item->product_id] = ($quantities[$item->product_id] ?? 0) + $item->quantity;
        }

        return [$quantities, $items->pluck('id')->all()];
    }

    private function validatedGuestCart(array $cart): array
    {
        $quantities = [];
        foreach ($cart as $productId => $quantity) {
            if (! ctype_digit((string) $productId) || ! is_int($quantity) || $quantity <= 0) {
                throw ValidationException::withMessages(['cart' => 'Cart quantities must be positive whole numbers.']);
            }
            $quantities[(int) $productId] = ($quantities[(int) $productId] ?? 0) + $quantity;
        }

        return $quantities;
    }

    private function effectivePriceMinor(Product $product): int
    {
        return max(0, $this->decimalToMinor((string) $product->price) - $this->decimalToMinor((string) $product->discount_amount));
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
}
