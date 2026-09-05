<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });

        DB::table('order_items')
            ->select(['id', 'product_id', 'product_name', 'product_sku'])
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $products = DB::table('products')
                    ->whereIn('id', $items->pluck('product_id')->filter()->unique())
                    ->get(['id', 'name', 'sku'])
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    $updates = [];
                    if ($item->product_name === null) {
                        $updates['product_name'] = $product?->name ?: 'Unavailable product';
                    }
                    if ($item->product_sku === null) {
                        $updates['product_sku'] = $product?->sku ?: 'SKU unavailable';
                    }
                    if (! $product) {
                        $updates['product_id'] = null;
                    }
                    if ($updates !== []) {
                        DB::table('order_items')->where('id', $item->id)->update($updates);
                    }
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable(false)->change();
            $table->string('product_sku')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Historical orders may now reference deleted customers or products; restore constraints manually from a verified backup.'
        );
    }
};
