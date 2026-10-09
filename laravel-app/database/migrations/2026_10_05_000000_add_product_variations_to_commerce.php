<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->string('variant_key', 80)->default('')->after('product_id');
            $table->string('variant_label', 120)->nullable()->after('variant_key');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique('cart_items_cart_id_product_id_unique');
            $table->unique(['cart_id', 'product_id', 'variant_key'], 'cart_items_cart_product_variant_unique');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('variant_key', 80)->nullable()->after('product_sku');
            $table->string('variant_label', 120)->nullable()->after('variant_key');
        });

        $lamp = DB::table('products')->where('slug', 'series-x-articulated-lamp')->first();
        if ($lamp) {
            $stock = max(0, (int) $lamp->stock);
            DB::table('products')->where('id', $lamp->id)->update([
                'name' => 'LED Swing-Arm Desk Lamp',
                'price' => '3332.00',
                'discount_amount' => '833.00',
                'compare_at_price' => null,
                'description' => 'Bring focused light exactly where your work moves. The articulated arm and adjustable head position easily for reading, study, detailed making, and everyday desk work, while the edge clamp keeps your workspace open.',
                'specs' => json_encode([
                    'Light source' => 'LED',
                    'Mounting' => 'Desk-edge clamp',
                    'Adjustment' => 'Articulated swing arm and adjustable lamp head',
                    'Light modes' => 'Warm, neutral, and white',
                    'Brightness' => 'Adjustable',
                    'Power connection' => 'USB',
                    'Controls' => 'In-line light controller',
                ], JSON_THROW_ON_ERROR),
                'variants' => json_encode([
                    ['key' => 'black', 'label' => 'Black', 'available' => $stock > 0, 'stock' => $stock],
                    ['key' => 'white', 'label' => 'White', 'available' => false, 'stock' => 0],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique('cart_items_cart_product_variant_unique');
            $table->dropColumn(['variant_key', 'variant_label']);
            $table->unique(['cart_id', 'product_id'], 'cart_items_cart_id_product_id_unique');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['variant_key', 'variant_label']);
        });
    }
};
