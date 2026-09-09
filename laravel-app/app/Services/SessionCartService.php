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

    public function add(Request $request, Product $product, int $quantity = 1): void
    {
        if ($customer = $request->user('web')) {
            $this->addForCustomer($customer, $product, $quantity);

            return;
        }

        $product = Product::published()->findOrFail($product->getKey());
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min($product->stock, ($cart[$product->id] ?? 0) + max(1, $quantity));
        $request->session()->put('cart', $cart);
        $this->guestMergeIdentity->markChanged($request);
    }

    public function update(Request $request, Product $product, int $quantity): void
    {
        if ($customer = $request->user('web')) {
            $this->setForCustomer($customer, $product, $quantity);

            return;
        }

        $product = Product::published()->findOrFail($product->getKey());
        $cart = $request->session()->get('cart', []);
        $cart[$product->id] = min($product->stock, $quantity);
        $request->session()->put('cart', $cart);
        $this->guestMergeIdentity->markChanged($request);
    }

    public function remove(Request $request, int $productId): void
    {
        if ($customer = $request->user('web')) {
            $this->customerTransaction(function () use ($customer, $productId): void {
                $this->lockCustomer($customer);
                $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
                if ($cart) {
                    CartItem::where('cart_id', $cart->id)->where('product_id', $productId)->lockForUpdate()->delete();
                }
            });

            return;
        }

        $cart = $request->session()->get('cart', []);
        unset($cart[$productId], $cart[(string) $productId]);
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

                    return [
                        'product_id' => (int) $item->product_id,
                        'product' => $product,
                        'available' => $product !== null,
                        'quantity' => $item->quantity,
                        'line_total' => $product ? $product->final_price * $item->quantity : 0,
                    ];
                })->values();
        }

        $cart = $request->session()->get('cart', []);
        $productIds = collect(array_keys($cart))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id);
        $products = Product::published()->whereIn('id', $productIds)->get()->keyBy('id');

        return collect($cart)->map(function ($quantity, int|string $productId) use ($products) {
            if (! ctype_digit((string) $productId) || ! is_int($quantity)) {
                return null;
            }

            $productId = (int) $productId;
            $product = $products->get($productId);

            return [
                'product_id' => $productId,
                'product' => $product,
                'available' => $product !== null,
                'quantity' => $quantity,
                'line_total' => $product ? $product->final_price * $quantity : 0,
            ];
        })->filter()->values();
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

    public function addForCustomer(User $customer, Product $product, int $quantity): void
    {
        $this->mutateCustomerProduct($customer, $product, fn (int $current, int $stock) => min($stock, $current + max(1, $quantity)));
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

    private function setForCustomer(User $customer, Product $product, int $quantity): void
    {
        $this->mutateCustomerProduct($customer, $product, fn (int $current, int $stock) => $quantity <= 0 ? 0 : min($stock, $quantity));
    }

    private function mutateCustomerProduct(User $customer, Product $product, callable $quantityResolver): void
    {
        $this->customerTransaction(function () use ($customer, $product, $quantityResolver): void {
            $this->lockCustomer($customer);
            $cart = Cart::where('user_id', $customer->id)->lockForUpdate()->first();
            $cart ??= Cart::create(['user_id' => $customer->id, 'session_id' => null]);
            $item = CartItem::where('cart_id', $cart->id)->where('product_id', $product->id)->lockForUpdate()->first();
            $lockedProduct = Product::published()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $quantity = $quantityResolver($item?->quantity ?? 0, $lockedProduct->stock);

            if ($quantity <= 0) {
                $item?->delete();

                return;
            }

            $item ??= new CartItem(['cart_id' => $cart->id, 'product_id' => $product->id]);
            $item->quantity = $quantity;
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
            || (str_contains($message, 'cart_items.cart_id') && str_contains($message, 'cart_items.product_id'));
    }
}
