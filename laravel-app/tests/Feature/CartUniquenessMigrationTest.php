<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CartUniquenessMigrationTest extends TestCase
{
    private string $originalConnection;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $path = tempnam(sys_get_temp_dir(), 'maat-cart-migration-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;
        config(['database.connections.cart_legacy' => [
            ...config('database.connections.sqlite'),
            'database' => $path,
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('cart_legacy');
        DB::setDefaultConnection('cart_legacy');
        $this->createLegacySchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('cart_legacy');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('cart_legacy');
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        parent::tearDown();
    }

    public function test_legacy_duplicates_are_deterministically_consolidated_before_constraints(): void
    {
        $this->seedIdentitiesAndProducts();
        DB::table('carts')->insert([
            ['id' => 10, 'user_id' => 1, 'session_id' => null],
            ['id' => 11, 'user_id' => 1, 'session_id' => 'legacy'],
            ['id' => 12, 'user_id' => 2, 'session_id' => null],
            ['id' => 20, 'user_id' => null, 'session_id' => 'anonymous-a'],
            ['id' => 21, 'user_id' => null, 'session_id' => 'anonymous-b'],
        ]);
        DB::table('cart_items')->insert([
            ['id' => 100, 'cart_id' => 10, 'product_id' => 1, 'quantity' => 2],
            ['id' => 101, 'cart_id' => 10, 'product_id' => 1, 'quantity' => 3],
            ['id' => 102, 'cart_id' => 11, 'product_id' => 1, 'quantity' => 4],
            ['id' => 103, 'cart_id' => 11, 'product_id' => 2, 'quantity' => 5],
            ['id' => 104, 'cart_id' => 12, 'product_id' => 1, 'quantity' => 6],
            ['id' => 105, 'cart_id' => 20, 'product_id' => 1, 'quantity' => 7],
            ['id' => 106, 'cart_id' => 20, 'product_id' => 1, 'quantity' => 8],
            ['id' => 107, 'cart_id' => 21, 'product_id' => 1, 'quantity' => 9],
        ]);

        $this->runTargetMigration();

        $this->assertSame([10], DB::table('carts')->where('user_id', 1)->pluck('id')->all());
        $this->assertDatabaseHas('cart_items', ['id' => 100, 'cart_id' => 10, 'product_id' => 1, 'quantity' => 9], 'cart_legacy');
        $this->assertDatabaseHas('cart_items', ['id' => 103, 'cart_id' => 10, 'product_id' => 2, 'quantity' => 5], 'cart_legacy');
        $this->assertDatabaseHas('cart_items', ['id' => 105, 'cart_id' => 20, 'product_id' => 1, 'quantity' => 15], 'cart_legacy');
        $this->assertDatabaseHas('cart_items', ['id' => 107, 'cart_id' => 21, 'product_id' => 1, 'quantity' => 9], 'cart_legacy');
        $this->assertSame(2, DB::table('carts')->whereNull('user_id')->count());
        $this->assertTrue(Schema::hasTable('cart_merge_attempts'));
        $this->assertContains('carts_user_id_unique', collect(Schema::getIndexes('carts'))->pluck('name')->all());
        $this->assertContains('cart_items_cart_id_product_id_unique', collect(Schema::getIndexes('cart_items'))->pluck('name')->all());
        $mergeIndexes = collect(Schema::getIndexes('cart_merge_attempts'))->pluck('name')->all();
        $this->assertContains('cart_merge_attempts_merge_key_unique', $mergeIndexes);
        $this->assertContains('cart_merge_attempts_user_id_index', $mergeIndexes);

        $this->expectUniqueViolation(fn () => DB::table('carts')->insert(['user_id' => 1]));
        $this->expectUniqueViolation(fn () => DB::table('cart_items')->insert(['cart_id' => 10, 'product_id' => 1, 'quantity' => 1]));
        DB::table('cart_merge_attempts')->insert([
            'user_id' => 1,
            'merge_key' => str_repeat('a', 64),
            'fingerprint' => str_repeat('b', 64),
            'completed_at' => now(),
        ]);
        $this->expectUniqueViolation(fn () => DB::table('cart_merge_attempts')->insert([
            'user_id' => 2,
            'merge_key' => str_repeat('a', 64),
            'fingerprint' => str_repeat('c', 64),
            'completed_at' => now(),
        ]));

        $quantities = DB::table('cart_items')->orderBy('id')->pluck('quantity', 'id')->all();
        $this->runTargetMigration();
        $this->assertSame($quantities, DB::table('cart_items')->orderBy('id')->pluck('quantity', 'id')->all());

        DB::table('products')->where('id', 2)->delete();
        $this->assertDatabaseMissing('cart_items', ['product_id' => 2], 'cart_legacy');
        DB::table('users')->where('id', 1)->delete();
        $this->assertDatabaseMissing('carts', ['user_id' => 1], 'cart_legacy');
        $this->assertDatabaseMissing('cart_merge_attempts', ['user_id' => 1], 'cart_legacy');
        $this->assertDatabaseHas('carts', ['user_id' => 2], 'cart_legacy');
    }

    public function test_invalid_or_overflowing_legacy_quantities_fail_without_partial_consolidation(): void
    {
        $this->seedIdentitiesAndProducts();
        DB::table('carts')->insert([['id' => 10, 'user_id' => 1], ['id' => 11, 'user_id' => 1]]);
        DB::table('cart_items')->insert([
            ['id' => 100, 'cart_id' => 10, 'product_id' => 1, 'quantity' => 2147483647],
            ['id' => 101, 'cart_id' => 11, 'product_id' => 1, 'quantity' => 1],
        ]);

        $migration = require database_path('migrations/2026_09_09_020000_enforce_cart_uniqueness_and_track_merges.php');
        try {
            $migration->up();
            $this->fail('Expected an actionable overflow failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('combined quantity for customer 1, product 1 exceeds', $exception->getMessage());
        }

        $this->assertSame([10, 11], DB::table('carts')->orderBy('id')->pluck('id')->all());
        $this->assertSame([2147483647, 1], DB::table('cart_items')->orderBy('id')->pluck('quantity')->all());
        $this->assertFalse(Schema::hasTable('cart_merge_attempts'));
    }

    private function createLegacySchema(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('products', fn (Blueprint $table) => $table->id());
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable();
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    private function seedIdentitiesAndProducts(): void
    {
        DB::table('users')->insert([['id' => 1], ['id' => 2]]);
        DB::table('products')->insert([['id' => 1], ['id' => 2]]);
    }

    private function runTargetMigration(): void
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_09_020000_enforce_cart_uniqueness_and_track_merges.php',
            '--force' => true,
        ])->assertSuccessful();
    }

    private function expectUniqueViolation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a database uniqueness violation.');
        } catch (UniqueConstraintViolationException) {
            $this->addToAssertionCount(1);
        }
    }
}
