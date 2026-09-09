<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartMergeAttempt;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartMergeService
{
    private const MAX_QUANTITY = 2147483647;

    public function __construct(private readonly GuestMergeIdentity $guestMergeIdentity) {}

    public function capture(Request $request): array
    {
        $cart = $this->validatedCart($request->session()->get('cart', []));
        $wishlist = $this->validatedWishlist($request->session()->get('wishlist', []));
        $mergeKey = $this->guestMergeIdentity->currentOrCreate($request);
        $payload = ['cart' => $cart, 'wishlist' => $wishlist];

        return [
            ...$payload,
            'merge_key' => $mergeKey,
            'fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    public function merge(Request $request, User $user, ?array $snapshot = null): void
    {
        $snapshot ??= $this->capture($request);
        $this->applySnapshot($user, $snapshot);
        $this->cleanupSnapshot($request, $snapshot);
    }

    public function applySnapshot(User $user, array $snapshot): bool
    {
        $this->validateCapturedSnapshot($snapshot);
        if ($snapshot['cart'] === [] && $snapshot['wishlist'] === []) {
            return false;
        }

        for ($attemptNumber = 1; $attemptNumber <= 3; $attemptNumber++) {
            try {
                return DB::transaction(function () use ($user, $snapshot): bool {
                    User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $attempt = CartMergeAttempt::where('merge_key', $snapshot['merge_key'])->lockForUpdate()->first();

                    if ($attempt) {
                        return $this->resolveCompletedAttempt($attempt, $user, $snapshot);
                    }

                    $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();
                    $cartItems = $cart
                        ? CartItem::where('cart_id', $cart->id)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id')
                        : collect();

                    $wishlist = Wishlist::where('user_id', $user->id)->orderBy('id')->lockForUpdate()->first();
                    $wishlist ??= Wishlist::create(['user_id' => $user->id, 'session_id' => null]);
                    $wishlistItems = WishlistItem::where('wishlist_id', $wishlist->id)
                        ->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');

                    $productIds = array_values(array_unique([
                        ...array_keys($snapshot['cart']),
                        ...$snapshot['wishlist'],
                    ]));
                    sort($productIds, SORT_NUMERIC);
                    $products = Product::published()->whereIn('id', $productIds)
                        ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                    foreach ($snapshot['cart'] as $productId => $quantity) {
                        $product = $products->get($productId);
                        if (! $product) {
                            continue;
                        }

                        $cart ??= Cart::create(['user_id' => $user->id, 'session_id' => null]);
                        $item = $cartItems->get($productId);
                        $mergedQuantity = min($product->stock, ($item?->quantity ?? 0) + $quantity);
                        if ($mergedQuantity <= 0) {
                            continue;
                        }

                        $item ??= new CartItem(['cart_id' => $cart->id, 'product_id' => $productId]);
                        $item->quantity = $mergedQuantity;
                        $item->save();
                        $cartItems->put($productId, $item);
                    }

                    foreach ($snapshot['wishlist'] as $productId) {
                        if ($products->has($productId) && ! $wishlistItems->has($productId)) {
                            $item = WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $productId]);
                            $wishlistItems->put($productId, $item);
                        }
                    }

                    CartMergeAttempt::create([
                        'user_id' => $user->id,
                        'merge_key' => $snapshot['merge_key'],
                        'fingerprint' => $snapshot['fingerprint'],
                        'completed_at' => now(),
                    ]);

                    return false;
                }, 3);
            } catch (QueryException $exception) {
                if ($this->isMergeIdentityUniquenessViolation($exception)) {
                    return DB::transaction(function () use ($user, $snapshot): bool {
                        User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                        $attempt = CartMergeAttempt::where('merge_key', $snapshot['merge_key'])->lockForUpdate()->first();
                        if (! $attempt) {
                            throw new \RuntimeException('The completed guest merge could not be resolved after contention.');
                        }

                        return $this->resolveCompletedAttempt($attempt, $user, $snapshot);
                    }, 3);
                }

                if ($attemptNumber === 3 || ! $this->isCartUniquenessViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('The guest merge could not be completed after bounded contention retries.');
    }

    private function resolveCompletedAttempt(CartMergeAttempt $attempt, User $user, array $snapshot): bool
    {
        if ((int) $attempt->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'cart' => 'This guest cart was already merged into a different customer account. Clear it before signing in to another account.',
            ]);
        }
        if (! hash_equals($attempt->fingerprint, $snapshot['fingerprint'])) {
            throw ValidationException::withMessages([
                'cart' => 'The guest cart changed during sign-in. Its contents were preserved; retry with a fresh sign-in.',
            ]);
        }

        return true;
    }

    private function isMergeIdentityUniquenessViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'cart_merge_attempts_merge_key_unique')
            || str_contains($message, 'cart_merge_attempts.merge_key');
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

    public function cleanupSnapshot(Request $request, array $snapshot): void
    {
        $currentCart = $request->session()->get('cart', []);
        if (is_array($currentCart)) {
            foreach ($snapshot['cart'] as $productId => $capturedQuantity) {
                if (! array_key_exists($productId, $currentCart) || ! is_int($currentCart[$productId])) {
                    continue;
                }

                if ($currentCart[$productId] > $capturedQuantity) {
                    $currentCart[$productId] -= $capturedQuantity;
                } elseif ($currentCart[$productId] === $capturedQuantity) {
                    unset($currentCart[$productId]);
                }
            }
            $currentCart === [] ? $request->session()->forget('cart') : $request->session()->put('cart', $currentCart);
        }

        $currentWishlist = $request->session()->get('wishlist', []);
        if (is_array($currentWishlist)) {
            $remainingWishlist = array_values(array_filter(
                $currentWishlist,
                fn ($productId) => ! in_array((int) $productId, $snapshot['wishlist'], true)
            ));
            $remainingWishlist === []
                ? $request->session()->forget('wishlist')
                : $request->session()->put('wishlist', $remainingWishlist);
        }

        if (! $request->session()->has('cart') && ! $request->session()->has('wishlist')) {
            $request->session()->forget(GuestMergeIdentity::SESSION_KEY);
        } elseif ($request->session()->get(GuestMergeIdentity::SESSION_KEY) === $snapshot['merge_key']) {
            $this->guestMergeIdentity->markChanged($request);
        }
    }

    private function validatedCart(mixed $cart): array
    {
        if (! is_array($cart)) {
            throw ValidationException::withMessages(['cart' => 'The guest cart session is invalid and was preserved for recovery.']);
        }

        $validated = [];
        foreach ($cart as $productId => $quantity) {
            if (! ctype_digit((string) $productId) || ! is_int($quantity) || $quantity <= 0 || $quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages(['cart' => 'Guest cart quantities must be positive whole numbers within the supported range.']);
            }
            $validated[(int) $productId] = $quantity;
        }
        ksort($validated, SORT_NUMERIC);

        return $validated;
    }

    private function validatedWishlist(mixed $wishlist): array
    {
        if (! is_array($wishlist)) {
            throw ValidationException::withMessages(['wishlist' => 'The guest wishlist session is invalid and was preserved for recovery.']);
        }

        $validated = [];
        foreach ($wishlist as $productId) {
            if (! ctype_digit((string) $productId)) {
                throw ValidationException::withMessages(['wishlist' => 'The guest wishlist session is invalid and was preserved for recovery.']);
            }
            $validated[] = (int) $productId;
        }
        $validated = array_values(array_unique($validated));
        sort($validated, SORT_NUMERIC);

        return $validated;
    }

    private function validateCapturedSnapshot(array $snapshot): void
    {
        if (! isset($snapshot['cart'], $snapshot['wishlist'], $snapshot['merge_key'], $snapshot['fingerprint'])
            || ! is_array($snapshot['cart']) || ! is_array($snapshot['wishlist'])
            || preg_match('/^[a-f0-9]{64}$/D', (string) $snapshot['merge_key']) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', (string) $snapshot['fingerprint']) !== 1) {
            throw new \InvalidArgumentException('A valid captured guest merge snapshot is required.');
        }

        $cart = $this->validatedCart($snapshot['cart']);
        $wishlist = $this->validatedWishlist($snapshot['wishlist']);
        if ($cart !== $snapshot['cart'] || $wishlist !== $snapshot['wishlist']) {
            throw new \InvalidArgumentException('The captured guest merge snapshot must use canonical product identifiers and quantities.');
        }

        $payload = ['cart' => $cart, 'wishlist' => $wishlist];
        $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        if (! hash_equals($fingerprint, $snapshot['fingerprint'])) {
            throw new \InvalidArgumentException('The captured guest merge snapshot fingerprint does not match.');
        }
    }
}
