<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderHistoryMigrationTest extends TestCase
{
    public function test_pre_step_four_orders_are_backfilled_and_preserved_on_upgrade(): void
    {
        $originalConnection = DB::getDefaultConnection();
        $database = tempnam(sys_get_temp_dir(), 'maat-step4-');
        config(['database.connections.history_upgrade' => [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('history_upgrade');
        DB::setDefaultConnection('history_upgrade');

        try {
            $this->createPreStepFourSchema();
            DB::table('users')->insert(['id' => 1]);
            DB::table('categories')->insert(['id' => 1]);
            DB::table('products')->insert([
                ['id' => 1, 'category_id' => 1, 'name' => 'Current Catalog Name', 'sku' => 'CURRENT-1'],
                ['id' => 2, 'category_id' => 1, 'name' => 'Backfilled Name', 'sku' => null],
            ]);
            DB::table('orders')->insert([
                'id' => 1, 'user_id' => 1, 'order_number' => 'PRE-STEP-4',
                'subtotal' => 270, 'shipping_fee' => 80, 'total' => 350,
            ]);
            DB::table('order_items')->insert([
                ['id' => 1, 'order_id' => 1, 'product_id' => 1, 'quantity' => 2, 'unit_price' => 90],
                ['id' => 2, 'order_id' => 1, 'product_id' => 2, 'quantity' => 1, 'unit_price' => 90],
            ]);

            $addSnapshots = require database_path('migrations/2026_09_06_010000_add_product_snapshots_to_order_items.php');
            $addSnapshots->up();
            DB::table('order_items')->where('id', 1)->update([
                'product_name' => 'Preserved Snapshot',
                'product_sku' => 'PRESERVED-1',
            ]);
            Schema::disableForeignKeyConstraints();
            DB::table('order_items')->insert([
                'id' => 3, 'order_id' => 1, 'product_id' => 999,
                'product_name' => null, 'product_sku' => null, 'quantity' => 1, 'unit_price' => 90,
            ]);
            Schema::enableForeignKeyConstraints();

            $preserveHistory = require database_path('migrations/2026_09_06_010100_backfill_order_item_snapshots_and_preserve_history.php');
            $preserveHistory->up();

            $this->assertSame(1, DB::table('orders')->count());
            $this->assertSame(350.0, (float) DB::table('orders')->value('total'));
            $this->assertSame(4, (int) DB::table('order_items')->sum('quantity'));
            $this->assertEqualsCanonicalizing([
                ['id' => 1, 'product_name' => 'Preserved Snapshot', 'product_sku' => 'PRESERVED-1', 'product_id' => 1],
                ['id' => 2, 'product_name' => 'Backfilled Name', 'product_sku' => 'SKU unavailable', 'product_id' => 2],
                ['id' => 3, 'product_name' => 'Unavailable product', 'product_sku' => 'SKU unavailable', 'product_id' => null],
            ], DB::table('order_items')->orderBy('id')->get(['id', 'product_name', 'product_sku', 'product_id'])
                ->map(fn ($item) => (array) $item)->all());

            DB::table('products')->where('id', 1)->delete();
            DB::table('users')->where('id', 1)->delete();
            $this->assertSame(1, DB::table('orders')->count());
            $this->assertNull(DB::table('orders')->value('user_id'));
            $this->assertSame(3, DB::table('order_items')->count());
            $this->assertNull(DB::table('order_items')->where('id', 1)->value('product_id'));
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('history_upgrade');
            @unlink($database);
        }
    }

    private function createPreStepFourSchema(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('categories', fn (Blueprint $table) => $table->id());
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2);
            $table->decimal('total', 10, 2);
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
        });
    }
}
