<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminTwoFactorChallenge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RotateAdministratorPassword extends Command
{
    protected $signature = 'admin:rotate-password {admin_id : Exact administrator ID}';

    protected $description = 'Securely set a new password for one administrator';

    public function handle(): int
    {
        $adminId = (string) $this->argument('admin_id');
        $identityValidator = Validator::make(['admin_id' => $adminId], [
            'admin_id' => ['required', 'regex:/^ADM-\d{4}-[A-Z]$/'],
        ]);

        if ($identityValidator->fails()) {
            $this->components->error($identityValidator->errors()->first());

            return self::FAILURE;
        }

        $admin = Admin::where('admin_id', $adminId)->first();

        if (! $admin) {
            $this->components->error('No administrator has that exact ID.');

            return self::FAILURE;
        }

        $this->line("Target: {$admin->admin_id} | {$admin->name} | {$admin->email}");

        $credentials = [
            'password' => $this->secret('New password (input hidden)'),
            'password_confirmation' => $this->secret('Confirm new password (input hidden)'),
        ];
        $validator = Validator::make($credentials, [
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($adminId, $credentials): void {
            $admin = Admin::where('admin_id', $adminId)->lockForUpdate()->firstOrFail();
            $admin->forceFill([
                'password' => Hash::make($credentials['password']),
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
                'remember_token' => Str::random(60),
                'session_version' => Str::random(64),
            ])->save();
            AdminTwoFactorChallenge::where('admin_id', $admin->id)
                ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
                ->update([
                    'status' => AdminTwoFactorChallenge::STATUS_INVALIDATED,
                    'code_hash' => '',
                    'invalidated_at' => now(),
                ]);
        }, 3);

        $this->components->info('The administrator password was rotated. Older admin sessions will be rejected on their next protected request.');

        return self::SUCCESS;
    }
}
