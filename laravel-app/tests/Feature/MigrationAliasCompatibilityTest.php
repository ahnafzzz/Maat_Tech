<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class MigrationAliasCompatibilityTest extends TestCase
{
    /** @var array<string, string> */
    private const ALIASES = [
        '2026_07_14_054521_create_categories_table' => '2026_07_14_054520_create_categories_table',
        '2026_07_14_054522_create_orders_table' => '2026_07_14_054523_create_orders_table',
        '2026_07_14_054522_create_cart_items_table' => '2026_07_14_054524_create_cart_items_table',
        '2026_07_14_054522_create_order_items_table' => '2026_07_14_054525_create_order_items_table',
    ];

    private const INTERMEDIATE_CART_ALIAS = '2026_01_03_000000_create_cart_items_table';

    private string $originalConnection;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        $path = tempnam(sys_get_temp_dir(), 'maat-migration-alias-');
        $this->assertNotFalse($path);
        $this->databasePath = $path;

        config(['database.connections.migration_alias_fixture' => [
            ...config('database.connections.sqlite'),
            'database' => $path,
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('migration_alias_fixture');
        DB::setDefaultConnection('migration_alias_fixture');
    }

    protected function tearDown(): void
    {
        DB::disconnect('migration_alias_fixture');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('migration_alias_fixture');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_fresh_database_migrates_and_reconciliation_is_a_no_op(): void
    {
        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();
        $this->migrate()->assertSuccessful();
        $this->assertCurrentAliasesOnly();

        $before = $this->migrationLedger();
        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();
        $this->migrate()->assertSuccessful();
        $this->assertSame($before, $this->migrationLedger());
    }

    public function test_original_names_reproduce_failure_then_reconcile_without_data_or_constraint_loss(): void
    {
        $this->migrate()->assertSuccessful();
        $this->seedRepresentativeCommerceData();
        $originalBatches = $this->replaceCurrentAliasesWithLegacy();

        try {
            $this->artisan('migrate', [
                '--database' => 'migration_alias_fixture',
                '--force' => true,
            ])->run();
            $this->fail('Expected the renamed categories migration to reproduce the legacy-ledger failure.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('categories', $exception->getMessage());
            $this->assertStringContainsString('already exists', $exception->getMessage());
        }
        $this->assertDatabaseHas('categories', ['id' => 10, 'slug' => 'legacy-category'], 'migration_alias_fixture');
        $this->assertLegacyAliasesOnly();

        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();
        $this->assertCurrentAliasesOnly();
        foreach (self::ALIASES as $legacy => $current) {
            $this->assertSame($originalBatches[$legacy], $this->migrationBatch($current));
        }

        $this->migrate()->assertSuccessful();
        $this->assertRepresentativeCommerceData();
        $this->assertRepresentativeConstraints();

        $before = $this->migrationLedger();
        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();
        $this->migrate()->assertSuccessful();
        $this->assertSame($before, $this->migrationLedger());
    }

    public function test_already_renamed_ledger_is_validated_without_being_changed(): void
    {
        $this->migrate()->assertSuccessful();
        $before = $this->migrationLedger();

        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();

        $this->assertSame($before, $this->migrationLedger());
        $this->assertCurrentAliasesOnly();
    }

    public function test_mixed_legacy_and_current_aliases_are_reconciled_with_each_original_batch(): void
    {
        $this->migrate()->assertSuccessful();
        $this->seedRepresentativeCommerceData();
        $legacySubset = array_slice(array_keys(self::ALIASES), 0, 2);
        $originalBatches = $this->replaceCurrentAliasesWithLegacy($legacySubset);

        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();
        $this->migrate()->assertSuccessful();

        $this->assertCurrentAliasesOnly();
        foreach ($legacySubset as $legacy) {
            $this->assertSame($originalBatches[$legacy], $this->migrationBatch(self::ALIASES[$legacy]));
        }
        $this->assertRepresentativeCommerceData();
        $this->assertRepresentativeConstraints();
    }

    public function test_intermediate_cart_alias_from_immediately_before_2f7ae1b_is_supported(): void
    {
        $this->migrate()->assertSuccessful();
        $current = self::ALIASES['2026_07_14_054522_create_cart_items_table'];
        $batch = $this->migrationBatch($current);
        DB::table('migrations')->where('migration', $current)->update([
            'migration' => self::INTERMEDIATE_CART_ALIAS,
        ]);

        $this->artisan('deployment:reconcile-migration-aliases')->assertSuccessful();

        $this->assertSame(0, DB::table('migrations')->where('migration', self::INTERMEDIATE_CART_ALIAS)->count());
        $this->assertSame($batch, $this->migrationBatch($current));
    }

    public function test_schema_mismatch_rejects_every_replacement_without_partial_reconciliation(): void
    {
        $this->migrate()->assertSuccessful();
        $legacySubset = array_slice(array_keys(self::ALIASES), 0, 2);
        $this->replaceCurrentAliasesWithLegacy($legacySubset);
        DB::statement('DROP INDEX categories_slug_unique');

        $this->artisan('deployment:reconcile-migration-aliases')
            ->assertFailed()
            ->expectsOutputToContain('missing the expected unique index');

        foreach ($legacySubset as $legacy) {
            $this->assertSame(1, DB::table('migrations')->where('migration', $legacy)->count());
            $this->assertSame(0, DB::table('migrations')->where('migration', self::ALIASES[$legacy])->count());
        }
    }

    public function test_duplicate_alias_pair_is_rejected_without_reconciling_other_legacy_rows(): void
    {
        $this->migrate()->assertSuccessful();
        $legacySubset = array_slice(array_keys(self::ALIASES), 0, 2);
        $this->replaceCurrentAliasesWithLegacy($legacySubset);
        $duplicateLegacy = $legacySubset[0];
        DB::table('migrations')->insert([
            'migration' => self::ALIASES[$duplicateLegacy],
            'batch' => $this->migrationBatch($duplicateLegacy),
        ]);

        $this->artisan('deployment:reconcile-migration-aliases')
            ->assertFailed()
            ->expectsOutputToContain('Multiple migration aliases are recorded');

        $otherLegacy = $legacySubset[1];
        $this->assertSame(1, DB::table('migrations')->where('migration', $otherLegacy)->count());
        $this->assertSame(0, DB::table('migrations')->where('migration', self::ALIASES[$otherLegacy])->count());
    }

    private function migrate(): PendingCommand
    {
        return $this->artisan('migrate', [
            '--database' => 'migration_alias_fixture',
            '--force' => true,
        ]);
    }

    /**
     * @param  list<string>|null  $legacyNames
     * @return array<string, int>
     */
    private function replaceCurrentAliasesWithLegacy(?array $legacyNames = null): array
    {
        $legacyNames ??= array_keys(self::ALIASES);
        $batches = [];

        foreach ($legacyNames as $legacy) {
            $current = self::ALIASES[$legacy];
            $batches[$legacy] = $this->migrationBatch($current);
            $this->assertSame(
                1,
                DB::table('migrations')->where('migration', $current)->update(['migration' => $legacy])
            );
        }

        return $batches;
    }

    private function seedRepresentativeCommerceData(): void
    {
        DB::table('users')->insert([
            'id' => 10,
            'name' => 'Legacy Customer',
            'email' => 'legacy@example.test',
            'password' => 'not-used',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'id' => 10,
            'name' => 'Legacy Category',
            'slug' => 'legacy-category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => 10,
            'category_id' => 10,
            'name' => 'Legacy Product',
            'slug' => 'legacy-product',
            'sku' => 'LEGACY-10',
            'price' => 125.50,
            'stock' => 4,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('carts')->insert([
            'id' => 10,
            'user_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cart_items')->insert([
            'id' => 10,
            'cart_id' => 10,
            'product_id' => 10,
            'quantity' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('orders')->insert([
            'id' => 10,
            'user_id' => 10,
            'order_number' => 'LEGACY-ORDER-10',
            'subtotal' => 251,
            'total' => 251,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'id' => 10,
            'order_id' => 10,
            'product_id' => 10,
            'product_name' => 'Legacy Product',
            'product_sku' => 'LEGACY-10',
            'quantity' => 2,
            'unit_price' => 125.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertRepresentativeCommerceData(): void
    {
        $this->assertDatabaseHas('categories', ['id' => 10, 'slug' => 'legacy-category'], 'migration_alias_fixture');
        $this->assertDatabaseHas('products', ['id' => 10, 'sku' => 'LEGACY-10'], 'migration_alias_fixture');
        $this->assertDatabaseHas('cart_items', ['id' => 10, 'quantity' => 2], 'migration_alias_fixture');
        $this->assertDatabaseHas('orders', ['id' => 10, 'order_number' => 'LEGACY-ORDER-10'], 'migration_alias_fixture');
        $this->assertDatabaseHas('order_items', [
            'id' => 10,
            'product_name' => 'Legacy Product',
            'product_sku' => 'LEGACY-10',
        ], 'migration_alias_fixture');
    }

    private function assertRepresentativeConstraints(): void
    {
        $schema = DB::connection()->getSchemaBuilder();
        $this->assertContains('categories_slug_unique', collect($schema->getIndexes('categories'))->pluck('name')->all());
        $this->assertContains('orders_order_number_unique', collect($schema->getIndexes('orders'))->pluck('name')->all());
        $this->assertContains('cart_items_cart_product_variant_unique', collect($schema->getIndexes('cart_items'))->pluck('name')->all());

        try {
            DB::table('categories')->insert([
                'name' => 'Duplicate',
                'slug' => 'legacy-category',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected the preserved category slug uniqueness constraint to reject a duplicate.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('users')->where('id', 10)->delete();
        $this->assertDatabaseHas('orders', ['id' => 10, 'user_id' => null], 'migration_alias_fixture');
        DB::table('products')->where('id', 10)->delete();
        $this->assertDatabaseMissing('cart_items', ['id' => 10], 'migration_alias_fixture');
        $this->assertDatabaseHas('order_items', ['id' => 10, 'product_id' => null], 'migration_alias_fixture');
    }

    private function assertCurrentAliasesOnly(): void
    {
        foreach (self::ALIASES as $legacy => $current) {
            $this->assertSame(0, DB::table('migrations')->where('migration', $legacy)->count());
            $this->assertSame(1, DB::table('migrations')->where('migration', $current)->count());
        }
    }

    private function assertLegacyAliasesOnly(): void
    {
        foreach (self::ALIASES as $legacy => $current) {
            $this->assertSame(1, DB::table('migrations')->where('migration', $legacy)->count());
            $this->assertSame(0, DB::table('migrations')->where('migration', $current)->count());
        }
    }

    /** @return array<int, array{migration: string, batch: int}> */
    private function migrationLedger(): array
    {
        return DB::table('migrations')->orderBy('id')->get(['migration', 'batch'])
            ->map(fn (object $row): array => ['migration' => $row->migration, 'batch' => $row->batch])
            ->all();
    }

    private function migrationBatch(string $migration): int
    {
        return (int) DB::table('migrations')->where('migration', $migration)->value('batch');
    }
}
