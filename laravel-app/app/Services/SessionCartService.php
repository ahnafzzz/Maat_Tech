<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionCartService
{
    public function __construct(private readonly GuestMergeIdentity $guestMergeIdentity) {}

    public function add(Request $request, Product $product, int $quantity = 1, ?string $variantKey = null): void
    {
        if ($customer = $request->user('web')) {
            $this->addForCustomer($customer, $product, $quantity, $variantKey);

            return;
        }

        $product = Product::published()->findOrFail($product->getKey());
        $variant = $this->validatedVariant($product, $variantKey);
        $cart = $request->session()->get('cart', []);
        if ($variant['key'] === '') {
            $desired = (int) ($cart[$product->id] ?? 0) + max(1, $quantity);
            $this->ensureStock($product, $variant, $desired);
            $cart[$product->id] = $desired;
        } else {
            $lineKey = $this->lineKey($product->id, $variant['key']);
            $current = is_array($cart[$lineKey] ?? null) ? (int) ($cart[$lineKey]['quantity'] ?? 0) : 0;
            $desired = $current + max(1, $quantity);
            $this->ensureStock($product, $variant, $desired);
            $cart[$lineKey] = [
                'product_id' => (int) $product->id,
                'variant_key' => $variant['key'],
                'variant_label' => $variant['label'],
                'quantity' => $desired,
            ];
        }
        $request->session()->put('cart', $cart);
        $this->guestMergeIdentity->markChanged($request);
    }

    public function update(Request $request, Product $product, int $quantity, ?string $variantKey = null): void
    {
        if ($customer = $request->user('web')) {
            $this->setForCustomer($customer, $product, $quantity, $variantKey);

            return;
        }

        $product = Product::published()->findOrFail($product->getKey());
        $variant = $this->validatedVariant($product, $variantKey);
        $cart = $request->session()->get('cart', []);
        $this->ensureStock($product, $variant, $quantity);
        if ($variant['key'] === '') {
            $cart[$product->id] = $quantity;
        } else {
            $lineKey = $this->lineKey($product->id, $variant['key']);
            $cart[$lineKey] = [
                'product_id' => (int) $product->id,
                'variant_key' => $variant['key'],
                'variant_label' => $variant['label'],
                'quantity' => $quantity,
            ];
        }
        $request->session()->put('cart', $cart);
        $this->guestMergeIdentity->markChanged($request);
    }

    public function remove(Request $request, int $productId, ?string $variantKey = null): void
    {
        if ($customer = $request->user('web')) {
            $this->customerTransaction(function () use ($customer, $productId, $variantKey): void {
                $this->lockCustomer($customer);
                $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
                if ($cart) {
                    CartItem::where('cart_id', $cart->id)->where('product_id', $productId)
                        ->when($variantKey !== null, fn ($query) => $query->where('variant_key', $variantKey))
                        ->lockForUpdate()->delete();
                }
            });

            return;
        }

        $cart = $request->session()->get('cart', []);
        if ($variantKey === null || $variantKey === '') {
            unset($cart[$productId], $cart[(string) $productId]);
        } else {
            unset($cart[$this->lineKey($productId, $variantKey)]);
        }
        $request->session()->put('cart', $cart);
        $this->guestMergeIdentity->markChanged($request);
    }

    public function items(Request $request): Collection
    {
        if ($customer = $request->user('web')) {
            return CartItem::whereHas('cart', fn ($query) => $query->where('user_id', $customer->id))
                ->with(['product' => fn ($query) => $query->published()])
                ->get()
                ->map(function (CartItem $item) {
                    $product = $item->product;
                    $variant = $product?->purchasableVariant($item->variant_key);

                    return [
                        'product_id' => (int) $item->product_id,
                        'product' => $product,
                        'available' => $product !== null && $variant !== null && $variant['available'] && $variant['stock'] >= $item->quantity,
                        'quantity' => $item->quantity,
                        'variant_key' => $item->variant_key ?: null,
                        'variant_label' => $item->variant_label,
                        'line_total' => $product ? $product->final_price * $item->quantity : 0,
                    ];
                })->values();
        }

        $cart = $request->session()->get('cart', []);
        $lines = $this->normalizeGuestCart($cart);
        $productIds = collect($lines)->pluck('product_id')->unique();
        $products = Product::published()->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($lines)->map(function (array $line) use ($products) {
            $productId = $line['product_id'];
            $product = $products->get($productId);
            $variant = $product?->purchasableVariant($line['variant_key']);

            return [
                'product_id' => $productId,
                'product' => $product,
                'available' => $product !== null && $variant !== null && $variant['available'] && $variant['stock'] >= $line['quantity'],
                'quantity' => $line['quantity'],
                'variant_key' => $line['variant_key'] ?: null,
                'variant_label' => $variant['label'] ?? $line['variant_label'],
                'line_total' => $product ? $product->final_price * $line['quantity'] : 0,
            ];
        })->values();
    }

    public function itemsForLines(array $lines): Collection
    {
        $normalized = $this->normalizeGuestCart($lines);
        $products = Product::published()->whereIn('id', collect($normalized)->pluck('product_id'))->get()->keyBy('id');

        return collect($normalized)->map(function (array $line) use ($products): array {
            $product = $products->get($line['product_id']);
            $variant = $product?->purchasableVariant($line['variant_key']);
            $available = $product && $variant && $variant['available'] && $variant['stock'] >= $line['quantity'];

            return [
                ...$line,
                'product' => $product,
                'available' => (bool) $available,
                'variant_label' => $variant['label'] ?? $line['variant_label'],
                'line_total' => $product ? $product->final_price * $line['quantity'] : 0,
            ];
        })->values();
    }

    public function clear(Request $request): void
    {
        if ($customer = $request->user('web')) {
            $this->customerTransaction(function () use ($customer): void {
                $this->lockCustomer($customer);
                $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
                if ($cart) {
                    CartItem::where('cart_id', $cart->id)->orderBy('product_id')->lockForUpdate()->get();
                    CartItem::where('cart_id', $cart->id)->delete();
                }
            });

            return;
        }

        $request->session()->forget('cart');
        $this->guestMergeIdentity->markChanged($request);
    }

    public function addForCustomer(User $customer, Product $product, int $quantity, ?string $variantKey = null): void
    {
        $this->mutateCustomerProduct($customer, $product, $variantKey, fn (int $current, int $stock) => $current + max(1, $quantity));
    }

    public function removeItemForCustomer(User $customer, int $itemId): void
    {
        $this->customerTransaction(function () use ($customer, $itemId): void {
            $this->lockCustomer($customer);
            $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
            $item = $cart ? CartItem::where('cart_id', $cart->id)->whereKey($itemId)->lockForUpdate()->first() : null;
            abort_unless($item, 404);
            $item->delete();
        });
    }

    private function setForCustomer(User $customer, Product $product, int $quantity, ?string $variantKey = null): void
    {
        $this->mutateCustomerProduct($customer, $product, $variantKey, fn (int $current, int $stock) => $quantity <= 0 ? 0 : $quantity);
    }

    private function mutateCustomerProduct(User $customer, Product $product, ?string $variantKey, callable $quantityResolver): void
    {
        $this->customerTransaction(function () use ($customer, $product, $variantKey, $quantityResolver): void {
            $this->lockCustomer($customer);
            $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
            $cart ??= Cart::create(['user_id' => $customer->id, 'session_id' => null]);
            $lockedProduct = Product::published()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $variant = $this->validatedVariant($lockedProduct, $variantKey);
            $item = CartItem::where('cart_id', $cart->id)->where('product_id', $product->id)
                ->where('variant_key', $variant['key'])->lockForUpdate()->first();
            $quantity = $quantityResolver($item?->quantity ?? 0, $variant['stock']);

            if ($quantity <= 0) {
                $item?->delete();

                return;
            }

            $this->ensureStock($lockedProduct, $variant, $quantity);

            $item ??= new CartItem(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_key' => $variant['key']]);
            $item->quantity = $quantity;
            $item->variant_label = $variant['label'];
            $item->save();
        });
    }

    private function lockCustomer(User $customer): User
    {
        return User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
    }

    private function customerTransaction(callable $callback): mixed
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction($callback, 3);
            } catch (QueryException $exception) {
                if ($attempt === 3 || ! $this->isCartUniquenessViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('The cart mutation could not be completed after bounded contention retries.');
    }

    private function isCartUniquenessViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'carts_user_id_unique')
            || str_contains($message, 'carts.user_id')
            || str_contains($message, 'cart_items_cart_id_product_id_unique')
            || str_contains($message, 'cart_items_cart_product_variant_unique')
            || (str_contains($message, 'cart_items.cart_id') && str_contains($message, 'cart_items.product_id'));
    }

    private function validatedVariant(Product $product, ?string $variantKey): array
    {
        $variant = $product->purchasableVariant($variantKey);
        if (! $variant || ! $variant['available'] || $variant['stock'] < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'variant_key' => 'Select an available product color.',
            ]);
        }

        return $variant;
    }

    private function ensureStock(Product $product, array $variant, int $quantity): void
    {
        if ($quantity > $variant['stock']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => "Only {$variant['stock']} unit(s) of {$product->name}".($variant['label'] ? " ({$variant['label']})" : '').' are available.',
            ]);
        }
    }

    private function lineKey(int $productId, string $variantKey): string
    {
        return $productId.'|'.$variantKey;
    }

    /** @return list<array{product_id:int,variant_key:string,variant_label:?string,quantity:int}> */
    private function normalizeGuestCart(mixed $cart): array
    {
        if (! is_array($cart)) {
            return [];
        }

        $lines = [];
        foreach ($cart as $key => $value) {
            if (ctype_digit((string) $key) && is_int($value) && $value > 0) {
                $lines[] = ['product_id' => (int) $key, 'variant_key' => '', 'variant_label' => null, 'quantity' => $value];
                continue;
            }
            if (! is_array($value) || ! isset($value['product_id'], $value['quantity'])
                || ! is_int($value['quantity']) || $value['quantity'] <= 0) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $value['product_id'],
                'variant_key' => (string) ($value['variant_key'] ?? ''),
                'variant_label' => isset($value['variant_label']) ? (string) $value['variant_label'] : null,
                'quantity' => $value['quantity'],
            ];
        }

        return $lines;
    }
}
