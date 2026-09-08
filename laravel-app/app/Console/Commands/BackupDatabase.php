<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'deployment:backup {directory : Empty directory outside the application tree}';

    protected $description = 'Create and verify a consistent pre-migration database backup';

    public function handle(): int
    {
        try {
            umask(0077);
            $directory = $this->prepareDirectory((string) $this->argument('directory'));
            $driver = DB::connection()->getDriverName();
            $artifact = match ($driver) {
                'sqlite' => $this->backupSqlite($directory),
                'mysql' => $this->backupMysql($directory),
                'pgsql' => $this->backupPostgres($directory),
                default => throw new RuntimeException('Unsupported database driver.'),
            };

            if (! is_file($artifact) || filesize($artifact) === 0) {
                throw new RuntimeException('The backup artifact is empty.');
            }

            if (! chmod($artifact, 0600)) {
                throw new RuntimeException('The backup artifact permissions could not be secured.');
            }

            $checksum = $artifact.'.sha256';

            if (file_put_contents($checksum, hash_file('sha256', $artifact).'  '.basename($artifact).PHP_EOL, LOCK_EX) === false
                || ! chmod($checksum, 0600)) {
                throw new RuntimeException('The backup checksum could not be written securely.');
            }
        } catch (Throwable $exception) {
            $this->components->error('Database backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Database backup created and verified.');

        return self::SUCCESS;
    }

    private function prepareDirectory(string $directory): string
    {
        if (! str_starts_with($directory, DIRECTORY_SEPARATOR) || str_starts_with($directory, base_path().DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Backup directory must be an absolute path outside the application tree.');
        }

        if (file_exists($directory)) {
            throw new RuntimeException('Backup directory already exists.');
        }

        if (! mkdir($directory, 0700, true) || ! is_writable($directory)) {
            throw new RuntimeException('Backup directory could not be created securely.');
        }

        return $directory;
    }

    private function backupSqlite(string $directory): string
    {
        $artifact = $directory.'/database.sqlite';
        $pdo = DB::connection()->getPdo();
        $pdo->exec('VACUUM INTO '.$pdo->quote($artifact));

        $backup = new PDO('sqlite:'.$artifact);
        $integrity = $backup->query('PRAGMA integrity_check')->fetchColumn();

        if ($integrity !== 'ok') {
            throw new RuntimeException('SQLite integrity verification failed.');
        }

        return $artifact;
    }

    private function backupMysql(string $directory): string
    {
        $artifact = $directory.'/database.sql';
        $config = DB::connection()->getConfig();
        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--host='.(string) ($config['host'] ?? ''),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--result-file='.$artifact,
            (string) ($config['database'] ?? ''),
        ];

        $this->runProcess($command, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);

        return $artifact;
    }

    private function backupPostgres(string $directory): string
    {
        $artifact = $directory.'/database.dump';
        $config = DB::connection()->getConfig();
        $environment = ['PGPASSWORD' => (string) ($config['password'] ?? '')];
        $this->runProcess([
            'pg_dump',
            '--format=custom',
            '--file='.$artifact,
            '--host='.(string) ($config['host'] ?? ''),
            '--port='.(string) ($config['port'] ?? '5432'),
            '--username='.(string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ], $environment);
        $this->runProcess(['pg_restore', '--list', $artifact], $environment);

        return $artifact;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function runProcess(array $command, array $environment): void
    {
        $process = new Process($command, null, $environment, null, 300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('The database backup tool returned a nonzero status.');
        }
    }
}
