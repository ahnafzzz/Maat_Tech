<?php

namespace App\Support;

use Illuminate\Database\Connection;
use RuntimeException;

class MigrationAliasReconciler
{
    /**
     * @var array<string, array{
     *     legacy: list<string>,
     *     table: string,
     *     columns: list<string>,
     *     unique: list<list<string>>,
     *     foreign: list<array{columns: list<string>, table: string, referenced: list<string>, on_delete: list<string>}>
     * }>
     */
    private const MIGRATIONS = [
        '2026_07_14_054520_create_categories_table' => [
            'legacy' => ['2026_07_14_054521_create_categories_table'],
            'table' => 'categories',
            'columns' => ['id', 'name', 'slug', 'description', 'created_at', 'updated_at'],
            'unique' => [['slug']],
            'foreign' => [],
        ],
        '2026_07_14_054523_create_orders_table' => [
            'legacy' => ['2026_07_14_054522_create_orders_table'],
            'table' => 'orders',
            'columns' => [
                'id', 'user_id', 'order_number', 'status', 'payment_method', 'shipping_method',
                'subtotal', 'shipping_fee', 'total', 'shipping_address', 'created_at', 'updated_at',
            ],
            'unique' => [['order_number']],
            'foreign' => [[
                'columns' => ['user_id'],
                'table' => 'users',
                'referenced' => ['id'],
                'on_delete' => ['cascade', 'set null'],
            ]],
        ],
        '2026_07_14_054524_create_cart_items_table' => [
            'legacy' => [
                '2026_07_14_054522_create_cart_items_table',
                '2026_01_03_000000_create_cart_items_table',
            ],
            'table' => 'cart_items',
            'columns' => ['id', 'cart_id', 'product_id', 'quantity', 'created_at', 'updated_at'],
            'unique' => [],
            'foreign' => [
                [
                    'columns' => ['cart_id'],
                    'table' => 'carts',
                    'referenced' => ['id'],
                    'on_delete' => ['cascade'],
                ],
                [
                    'columns' => ['product_id'],
                    'table' => 'products',
                    'referenced' => ['id'],
                    'on_delete' => ['cascade'],
                ],
            ],
        ],
        '2026_07_14_054525_create_order_items_table' => [
            'legacy' => ['2026_07_14_054522_create_order_items_table'],
            'table' => 'order_items',
            'columns' => ['id', 'order_id', 'product_id', 'quantity', 'unit_price', 'created_at', 'updated_at'],
            'unique' => [],
            'foreign' => [
                [
                    'columns' => ['order_id'],
                    'table' => 'orders',
                    'referenced' => ['id'],
                    'on_delete' => ['cascade'],
                ],
                [
                    'columns' => ['product_id'],
                    'table' => 'products',
                    'referenced' => ['id'],
                    'on_delete' => ['cascade', 'set null'],
                ],
            ],
        ],
    ];

    /**
     * Replace verified legacy migration names with their current aliases.
     *
     * @return array<string, string>
     */
    public function reconcile(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable('migrations')) {
            $unexpectedTables = collect(self::MIGRATIONS)
                ->pluck('table')
                ->filter(fn (string $table): bool => $schema->hasTable($table))
                ->values()
                ->all();

            if ($unexpectedTables !== []) {
                throw new RuntimeException(
                    'The migrations ledger is absent while aliased tables exist: '.implode(', ', $unexpectedTables).'.'
                );
            }

            return [];
        }

        $names = collect(self::MIGRATIONS)
            ->flatMap(fn (array $definition, string $current): array => [$current, ...$definition['legacy']])
            ->all();
        $rows = $connection->table('migrations')
            ->whereIn('migration', $names)
            ->orderBy('id')
            ->get(['id', 'migration', 'batch'])
            ->groupBy('migration');
        $replacements = [];

        foreach (self::MIGRATIONS as $current => $definition) {
            $aliases = [$current, ...$definition['legacy']];
            $recorded = collect($aliases)->filter(function (string $alias) use ($rows): bool {
                $count = $rows->get($alias, collect())->count();

                if ($count > 1) {
                    throw new RuntimeException("Duplicate migration ledger rows exist for the verified alias {$alias}.");
                }

                return $count === 1;
            })->values();

            if ($recorded->count() > 1) {
                throw new RuntimeException(
                    "Multiple migration aliases are recorded for {$definition['table']}: ".$recorded->implode(', ').'.'
                );
            }

            if ($recorded->isEmpty()) {
                if ($schema->hasTable($definition['table'])) {
                    throw new RuntimeException(
                        "Table {$definition['table']} exists without either verified migration alias in the ledger."
                    );
                }

                continue;
            }

            $this->validateSchema($connection, $definition);

            $recordedAlias = $recorded->sole();
            if ($recordedAlias !== $current) {
                $replacements[$recordedAlias] = $current;
            }
        }

        if ($replacements === []) {
            return [];
        }

        $connection->transaction(function () use ($connection, $replacements): void {
            $lockedRows = $connection->table('migrations')
                ->whereIn('migration', array_merge(array_keys($replacements), array_values($replacements)))
                ->lockForUpdate()
                ->get(['id', 'migration', 'batch'])
                ->groupBy('migration');

            foreach ($replacements as $legacy => $current) {
                if (($lockedRows->get($legacy)?->count() ?? 0) !== 1
                    || ($lockedRows->get($current)?->count() ?? 0) !== 0) {
                    throw new RuntimeException("Migration aliases changed during reconciliation for {$legacy} / {$current}.");
                }
            }

            foreach ($replacements as $legacy => $current) {
                $affected = $connection->table('migrations')
                    ->where('migration', $legacy)
                    ->update(['migration' => $current]);

                if ($affected !== 1) {
                    throw new RuntimeException("Could not reconcile migration alias {$legacy} / {$current}.");
                }
            }
        });

        return $replacements;
    }

    /**
     * @param  array{
     *     legacy: list<string>,
     *     table: string,
     *     columns: list<string>,
     *     unique: list<list<string>>,
     *     foreign: list<array{columns: list<string>, table: string, referenced: list<string>, on_delete: list<string>}>
     * }  $definition
     */
    private function validateSchema(Connection $connection, array $definition): void
    {
        $schema = $connection->getSchemaBuilder();
        $table = $definition['table'];

        if (! $schema->hasTable($table)) {
            throw new RuntimeException("Migration alias is recorded but expected table {$table} is missing.");
        }

        $columns = collect($schema->getColumns($table))->pluck('name')->map('strtolower')->all();
        $missingColumns = array_diff($definition['columns'], $columns);

        if ($missingColumns !== []) {
            throw new RuntimeException(
                "Table {$table} is missing columns required by its verified migration alias: ".implode(', ', $missingColumns).'.'
            );
        }

        $indexes = collect($schema->getIndexes($table));
        foreach ($definition['unique'] as $expectedColumns) {
            $found = $indexes->contains(function (array $index) use ($expectedColumns): bool {
                $columns = array_map('strtolower', $index['columns'] ?? []);

                return ($index['unique'] ?? false) === true && $columns === $expectedColumns;
            });

            if (! $found) {
                throw new RuntimeException(
                    "Table {$table} is missing the expected unique index on ".implode(', ', $expectedColumns).'.'
                );
            }
        }

        $foreignKeys = collect($schema->getForeignKeys($table));
        foreach ($definition['foreign'] as $expected) {
            $found = $foreignKeys->contains(function (array $foreign) use ($expected): bool {
                $columns = array_map('strtolower', $foreign['columns'] ?? []);
                $referenced = array_map('strtolower', $foreign['foreign_columns'] ?? []);
                $onDelete = strtolower((string) ($foreign['on_delete'] ?? ''));

                return $columns === $expected['columns']
                    && strtolower((string) ($foreign['foreign_table'] ?? '')) === $expected['table']
                    && $referenced === $expected['referenced']
                    && in_array($onDelete, $expected['on_delete'], true);
            });

            if (! $found) {
                throw new RuntimeException(
                    "Table {$table} is missing the expected foreign key from "
                    .implode(', ', $expected['columns'])." to {$expected['table']}."
                );
            }
        }
    }
}
