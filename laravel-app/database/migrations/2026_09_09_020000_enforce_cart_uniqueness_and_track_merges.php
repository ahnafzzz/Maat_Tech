<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_QUANTITY = 2147483647;

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->validateLegacyQuantities();
            $this->consolidateCustomerCarts();
            $this->consolidateDuplicateItems();
        }, 3);

        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id', 'carts_user_id_unique');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_id_product_id_unique');
        });

        Schema::create('cart_merge_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merge_key', 64);
            $table->string('fingerprint', 64);
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique('merge_key', 'cart_merge_attempts_merge_key_unique');
            $table->index('user_id', 'cart_merge_attempts_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_merge_attempts');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_items_cart_id_product_id_unique');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_user_id_unique');
        });
    }

    private function validateLegacyQuantities(): void
    {
        DB::table('cart_items')->orderBy('id')->get(['id', 'quantity'])->each(function (object $item): void {
            $quantity = (string) $item->quantity;
            if (! preg_match('/^\d+$/D', $quantity) || $quantity === '0' || $this->exceedsMaximum($quantity)) {
                throw new RuntimeException(
                    "Cart uniqueness migration stopped: cart item {$item->id} has invalid quantity {$quantity}; correct it to a positive integer no greater than ".self::MAX_QUANTITY.' and rerun the migration.'
                );
            }
        });
    }

    private function consolidateCustomerCarts(): void
    {
        $userIds = DB::table('carts')->whereNotNull('user_id')
            ->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')
            ->orderBy('user_id')->pluck('user_id');

        foreach ($userIds as $userId) {
            $cartIds = DB::table('carts')->where('user_id', $userId)->orderBy('id')->pluck('id');
            $canonicalCartId = (int) $cartIds->first();
            $items = DB::table('cart_items')->whereIn('cart_id', $cartIds)->orderBy('product_id')->orderBy('id')->get();

            foreach ($items->groupBy('product_id') as $productId => $productItems) {
                $quantity = $this->summedQuantity($productItems, "customer {$userId}, product {$productId}");
                $keeper = $productItems->firstWhere('cart_id', $canonicalCartId) ?? $productItems->first();
                DB::table('cart_items')->whereIn('id', $productItems->pluck('id')->reject(fn ($id) => (int) $id === (int) $keeper->id))->delete();
                DB::table('cart_items')->where('id', $keeper->id)->update([
                    'cart_id' => $canonicalCartId,
                    'quantity' => $quantity,
                    'updated_at' => now(),
                ]);
            }

            DB::table('carts')->whereIn('id', $cartIds->slice(1))->delete();
        }
    }

    private function consolidateDuplicateItems(): void
    {
        $groups = DB::table('cart_items')->select('cart_id', 'product_id')
            ->groupBy('cart_id', 'product_id')->havingRaw('COUNT(*) > 1')
            ->orderBy('cart_id')->orderBy('product_id')->get();

        foreach ($groups as $group) {
            $items = DB::table('cart_items')->where('cart_id', $group->cart_id)
                ->where('product_id', $group->product_id)->orderBy('id')->get();
            $quantity = $this->summedQuantity($items, "cart {$group->cart_id}, product {$group->product_id}");
            $keeper = $items->first();
            DB::table('cart_items')->whereIn('id', $items->pluck('id')->slice(1))->delete();
            DB::table('cart_items')->where('id', $keeper->id)->update([
                'quantity' => $quantity,
                'updated_at' => now(),
            ]);
        }
    }

    private function summedQuantity(iterable $items, string $context): int
    {
        $total = 0;
        foreach ($items as $item) {
            $quantity = (int) $item->quantity;
            if ($quantity > self::MAX_QUANTITY - $total) {
                throw new RuntimeException(
                    "Cart uniqueness migration stopped: combined quantity for {$context} exceeds ".self::MAX_QUANTITY.'; split or correct the legacy data and rerun the migration.'
                );
            }
            $total += $quantity;
        }

        return $total;
    }

    private function exceedsMaximum(string $quantity): bool
    {
        $maximum = (string) self::MAX_QUANTITY;

        return strlen($quantity) > strlen($maximum)
            || (strlen($quantity) === strlen($maximum) && strcmp($quantity, $maximum) > 0);
    }
};
