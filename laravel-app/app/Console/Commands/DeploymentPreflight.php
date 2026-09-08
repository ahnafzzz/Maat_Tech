<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DeploymentPreflight extends Command
{
    protected $signature = 'deployment:preflight';

    protected $description = 'Validate production configuration before replacing the deployed application';

    public function handle(): int
    {
        $errors = [];
        $driver = (string) config('database.default');

        if (! app()->environment('production')) {
            $errors[] = 'APP_ENV must be production.';
        }

        if ((bool) config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false.';
        }

        $key = (string) config('app.key');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        if (! is_string($decodedKey) || ! Encrypter::supported($decodedKey, (string) config('app.cipher'))) {
            $errors[] = 'APP_KEY is missing or invalid.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $errors[] = 'APP_URL must use HTTPS.';
        }

        if (config('session.secure') !== true) {
            $errors[] = 'SESSION_SECURE_COOKIE must be true.';
        }

        foreach ((array) config('trustedproxy.proxies', []) as $proxy) {
            if (! $this->isValidTrustedProxy((string) $proxy)) {
                $errors[] = 'TRUSTED_PROXIES must contain only explicit IP addresses or CIDRs.';
                break;
            }
        }

        if (! in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            $errors[] = 'DB_CONNECTION must be sqlite, mysql, or pgsql for the maintained deployment scripts.';
        }

        if ($driver === 'sqlite') {
            $database = (string) config('database.connections.sqlite.database');
            $absolute = str_starts_with($database, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $database) === 1;

            if (! $absolute || str_starts_with($database, base_path().DIRECTORY_SEPARATOR)) {
                $errors[] = 'Production SQLite DB_DATABASE must be an absolute path outside the application directory.';
            } elseif (! is_file($database)) {
                $errors[] = 'The configured production SQLite database does not exist.';
            }
        }

        foreach ([storage_path(), storage_path('app/public'), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $errors[] = 'Required runtime directories are missing or not writable.';
                break;
            }
        }

        if (! is_file(public_path('build/manifest.json'))) {
            $errors[] = 'Built frontend assets are missing.';
        }

        if (! is_link(public_path('storage')) || ! is_dir(public_path('storage'))) {
            $errors[] = 'The public storage link is missing or invalid.';
        }

        try {
            DB::connection()->getPdo();

            if (! Schema::hasTable('migrations')) {
                $errors[] = 'The database is not initialized; use the documented first-install procedure.';
            }
        } catch (Throwable) {
            $errors[] = 'The configured database is not reachable.';
        }

        if ($errors !== []) {
            foreach (array_unique($errors) as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info('Production deployment preflight passed.');

        return self::SUCCESS;
    }

    private function isValidTrustedProxy(string $proxy): bool
    {
        if (in_array($proxy, ['*', '**', 'REMOTE_ADDR'], true)) {
            return false;
        }

        [$address, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $maximum = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix <= $maximum;
    }
}
