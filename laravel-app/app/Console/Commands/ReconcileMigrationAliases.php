<?php

namespace App\Console\Commands;

use App\Support\MigrationAliasReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcileMigrationAliases extends Command
{
    protected $signature = 'deployment:reconcile-migration-aliases
        {--database= : The database connection to reconcile}';

    protected $description = 'Validate and reconcile the migration filename aliases leading to commit 2f7ae1b';

    public function handle(MigrationAliasReconciler $reconciler): int
    {
        $connectionName = $this->option('database');
        $connection = DB::connection(is_string($connectionName) && $connectionName !== '' ? $connectionName : null);

        try {
            $replacements = $reconciler->reconcile($connection);
        } catch (Throwable $exception) {
            $this->components->error('Migration alias reconciliation refused: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($replacements === []) {
            $this->components->info('Migration aliases already agree with the verified schema; no ledger changes were needed.');

            return self::SUCCESS;
        }

        foreach ($replacements as $legacy => $current) {
            $this->components->twoColumnDetail($legacy, $current);
        }

        $this->components->info('Verified migration aliases reconciled without changing their batches.');

        return self::SUCCESS;
    }
}
