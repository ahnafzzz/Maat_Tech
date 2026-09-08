<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/maat-deployment-backup-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        DB::purge('deployment_backup_test');

        if (is_dir($this->temporaryDirectory)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->temporaryDirectory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_sqlite_backup_is_consistent_verified_and_keeps_source_untouched(): void
    {
        $source = $this->temporaryDirectory.'/source.sqlite';
        $pdo = new PDO('sqlite:'.$source);
        $pdo->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec("INSERT INTO records (value) VALUES ('persistent-data')");
        unset($pdo);

        config([
            'database.default' => 'deployment_backup_test',
            'database.connections.deployment_backup_test' => [
                'driver' => 'sqlite',
                'database' => $source,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $command = $this->app->make(BackupDatabase::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $backupDirectory = $this->temporaryDirectory.'/verified-backup';

        $this->assertSame(Command::SUCCESS, $tester->execute(['directory' => $backupDirectory]));
        $this->assertFileExists($backupDirectory.'/database.sqlite');
        $this->assertFileExists($backupDirectory.'/database.sqlite.sha256');
        $this->assertSame('persistent-data', (new PDO('sqlite:'.$backupDirectory.'/database.sqlite'))->query('SELECT value FROM records')->fetchColumn());
        $this->assertSame('persistent-data', (new PDO('sqlite:'.$source))->query('SELECT value FROM records')->fetchColumn());
    }

    public function test_backup_refuses_an_existing_destination_without_overwriting_it(): void
    {
        $destination = $this->temporaryDirectory.'/existing';
        mkdir($destination, 0700);
        file_put_contents($destination.'/sentinel', 'keep');
        $command = $this->app->make(BackupDatabase::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $this->assertSame(Command::FAILURE, $tester->execute(['directory' => $destination]));
        $this->assertSame('keep', file_get_contents($destination.'/sentinel'));
    }
}
