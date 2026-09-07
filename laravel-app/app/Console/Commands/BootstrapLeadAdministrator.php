<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BootstrapLeadAdministrator extends Command
{
    protected $signature = 'admin:bootstrap
        {--admin-id= : Administrator ID in ADM-0000-X format}
        {--name= : Administrator name}
        {--email= : Administrator email address}';

    protected $description = 'Create the first lead administrator securely';

    public function handle(): int
    {
        $identity = [
            'admin_id' => $this->option('admin-id') ?: $this->ask('Admin ID'),
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => $this->option('email') ?: $this->ask('Email'),
        ];

        $validator = Validator::make($identity, [
            'admin_id' => ['required', 'regex:/^ADM-\d{4}-[A-Z]$/'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        if ($message = $this->existingAccountConflict($identity)) {
            $this->components->error($message);

            return self::FAILURE;
        }

        $credentials = [
            'password' => $this->secret('Password (input hidden)'),
            'password_confirmation' => $this->secret('Confirm password (input hidden)'),
        ];
        $passwordValidator = Validator::make($credentials, [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($passwordValidator->fails()) {
            $this->components->error($passwordValidator->errors()->first());

            return self::FAILURE;
        }

        try {
            $message = Cache::lock('admin:first-lead-bootstrap', 15)->block(5, function () use ($identity, $credentials): ?string {
                return DB::transaction(function () use ($identity, $credentials): ?string {
                    if ($message = $this->existingAccountConflict($identity, true)) {
                        return $message;
                    }

                    Admin::create([
                        ...$identity,
                        'password' => Hash::make($credentials['password']),
                        'is_lead' => true,
                        'status' => 'active',
                        'session_version' => Str::random(64),
                    ]);

                    return null;
                }, 3);
            });
        } catch (LockTimeoutException) {
            $this->components->error('Another administrator bootstrap is in progress. Try again.');

            return self::FAILURE;
        } catch (QueryException) {
            $this->components->error('The administrator could not be created because its identity is no longer unique.');

            return self::FAILURE;
        }

        if ($message) {
            $this->components->error($message);

            return self::FAILURE;
        }

        $this->components->info('The first lead administrator was created.');

        return self::SUCCESS;
    }

    /**
     * @param  array{admin_id: mixed, name: mixed, email: mixed}  $identity
     */
    private function existingAccountConflict(array $identity, bool $lock = false): ?string
    {
        $query = Admin::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        if ((clone $query)->where('is_lead', true)->exists()) {
            return 'A lead administrator already exists.';
        }

        if ((clone $query)->where('admin_id', $identity['admin_id'])->exists()) {
            return 'That administrator ID is already in use.';
        }

        if ((clone $query)->where('email', $identity['email'])->exists()) {
            return 'That administrator email is already in use.';
        }

        return null;
    }
}
