<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminTwoFactorChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminTwoFactorService
{
    public const PENDING_SELECTOR_KEY = 'pending_admin_two_factor_selector';

    public const PENDING_BINDING_KEY = 'pending_admin_two_factor_binding';

    public function createChallenge(Admin $candidate, string $password, ?string $previousSelector, ?string $previousBinding): array
    {
        return DB::transaction(function () use ($candidate, $password, $previousSelector, $previousBinding): array {
            $admin = Admin::whereKey($candidate->id)->lockForUpdate()->first();

            if (! $admin || ! $admin->isActive() || ! $admin->two_factor_enabled || ! Hash::check($password, $admin->password)) {
                return ['status' => 'invalid_credentials'];
            }

            if (! $admin->session_version) {
                $admin->forceFill(['session_version' => Str::random(64)])->save();
            }

            $recentCount = AdminTwoFactorChallenge::where('admin_id', $admin->id)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentCount >= (int) config('admin.two_factor_max_challenges_per_hour', 5)) {
                return ['status' => 'rate_limited'];
            }

            $this->supersedeBoundChallenge($admin->id, $previousSelector, $previousBinding);

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $binding = bin2hex(random_bytes(32));
            $challenge = AdminTwoFactorChallenge::create([
                'admin_id' => $admin->id,
                'selector' => bin2hex(random_bytes(16)),
                'code_hash' => Hash::make($code),
                'session_binding_hash' => hash('sha256', $binding),
                'credential_version' => $admin->session_version,
                'status' => AdminTwoFactorChallenge::STATUS_PENDING,
                'expires_at' => now()->addMinutes((int) config('admin.two_factor_expiration_minutes', 10)),
            ]);

            $admin->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();

            return compact('admin', 'challenge', 'code', 'binding') + ['status' => 'created'];
        }, 3);
    }

    public function markDelivered(AdminTwoFactorChallenge $challenge): void
    {
        $updated = AdminTwoFactorChallenge::whereKey($challenge->id)
            ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);

        if ($updated !== 1) {
            throw new \RuntimeException('The two-factor challenge could not be marked delivered.');
        }
    }

    public function markDeliveryFailed(AdminTwoFactorChallenge $challenge): void
    {
        AdminTwoFactorChallenge::whereKey($challenge->id)
            ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
            ->update([
                'status' => AdminTwoFactorChallenge::STATUS_DELIVERY_FAILED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ]);
    }

    public function inspect(?string $selector, ?string $binding): string
    {
        $located = $this->locate($selector, $binding);

        if (! $located) {
            return 'missing';
        }

        return DB::transaction(function () use ($located, $binding): string {
            $admin = Admin::whereKey($located->admin_id)->lockForUpdate()->first();
            $challenge = AdminTwoFactorChallenge::whereKey($located->id)->lockForUpdate()->first();

            return $this->currentState($admin, $challenge, (string) $binding);
        }, 3);
    }

    public function verify(?string $selector, ?string $binding, mixed $submittedCode): array
    {
        $located = $this->locate($selector, $binding);

        if (! $located) {
            return ['status' => 'missing'];
        }

        return DB::transaction(function () use ($located, $binding, $submittedCode): array {
            $admin = Admin::whereKey($located->admin_id)->lockForUpdate()->first();
            $challenge = AdminTwoFactorChallenge::whereKey($located->id)->lockForUpdate()->first();
            $state = $this->currentState($admin, $challenge, (string) $binding);

            if ($state !== AdminTwoFactorChallenge::STATUS_PENDING) {
                return ['status' => $state];
            }

            $code = is_string($submittedCode) ? $submittedCode : '';
            $wellFormed = preg_match('/^\d{6}$/D', $code) === 1;

            if ($wellFormed && Hash::check($code, $challenge->code_hash)) {
                $challenge->forceFill([
                    'status' => AdminTwoFactorChallenge::STATUS_CONSUMED,
                    'code_hash' => '',
                    'consumed_at' => now(),
                ])->save();

                return ['status' => 'verified', 'admin' => $admin];
            }

            $attempts = $challenge->failed_attempts + 1;
            $exhausted = $attempts >= (int) config('admin.two_factor_max_attempts', 5);
            $challenge->forceFill([
                'failed_attempts' => $attempts,
                'status' => $exhausted ? AdminTwoFactorChallenge::STATUS_EXHAUSTED : AdminTwoFactorChallenge::STATUS_PENDING,
                'code_hash' => $exhausted ? '' : $challenge->code_hash,
                'invalidated_at' => $exhausted ? now() : null,
            ])->save();

            return [
                'status' => $exhausted ? AdminTwoFactorChallenge::STATUS_EXHAUSTED : ($wellFormed ? 'incorrect' : 'malformed'),
                'remaining_attempts' => max(0, (int) config('admin.two_factor_max_attempts', 5) - $attempts),
            ];
        }, 3);
    }

    public function toggle(Admin $candidate): Admin
    {
        return DB::transaction(function () use ($candidate): Admin {
            $admin = Admin::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            $admin->forceFill([
                'two_factor_enabled' => ! $admin->two_factor_enabled,
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ])->save();
            $this->invalidatePendingForAdmin($admin->id);

            return $admin;
        }, 3);
    }

    public function invalidatePendingForAdmin(int $adminId): void
    {
        AdminTwoFactorChallenge::where('admin_id', $adminId)
            ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
            ->update([
                'status' => AdminTwoFactorChallenge::STATUS_INVALIDATED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ]);
    }

    public function supersedeBound(?string $selector, ?string $binding): void
    {
        if (! is_string($selector) || ! is_string($binding)) {
            return;
        }

        AdminTwoFactorChallenge::where('selector', $selector)
            ->where('session_binding_hash', hash('sha256', $binding))
            ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
            ->update([
                'status' => AdminTwoFactorChallenge::STATUS_SUPERSEDED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ]);
    }

    private function locate(?string $selector, ?string $binding): ?AdminTwoFactorChallenge
    {
        if (! is_string($selector) || ! preg_match('/^[a-f0-9]{32}$/D', $selector) || ! is_string($binding)) {
            return null;
        }

        return AdminTwoFactorChallenge::where('selector', $selector)
            ->where('session_binding_hash', hash('sha256', $binding))
            ->first();
    }

    private function currentState(?Admin $admin, ?AdminTwoFactorChallenge $challenge, string $binding): string
    {
        if (! $admin || ! $challenge || ! hash_equals($challenge->session_binding_hash, hash('sha256', $binding))) {
            return 'missing';
        }

        if ($challenge->status !== AdminTwoFactorChallenge::STATUS_PENDING) {
            return $challenge->status;
        }

        if (now()->greaterThanOrEqualTo($challenge->expires_at)) {
            $challenge->forceFill([
                'status' => AdminTwoFactorChallenge::STATUS_EXPIRED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ])->save();

            return AdminTwoFactorChallenge::STATUS_EXPIRED;
        }

        if (! $challenge->delivered_at || ! $admin->isActive() || ! $admin->two_factor_enabled || ! is_string($admin->session_version) || ! hash_equals($admin->session_version, $challenge->credential_version)) {
            $challenge->forceFill([
                'status' => AdminTwoFactorChallenge::STATUS_INVALIDATED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ])->save();

            return AdminTwoFactorChallenge::STATUS_INVALIDATED;
        }

        return AdminTwoFactorChallenge::STATUS_PENDING;
    }

    private function supersedeBoundChallenge(int $adminId, ?string $selector, ?string $binding): void
    {
        if (! is_string($selector) || ! is_string($binding)) {
            return;
        }

        AdminTwoFactorChallenge::where('admin_id', $adminId)
            ->where('selector', $selector)
            ->where('session_binding_hash', hash('sha256', $binding))
            ->where('status', AdminTwoFactorChallenge::STATUS_PENDING)
            ->update([
                'status' => AdminTwoFactorChallenge::STATUS_SUPERSEDED,
                'code_hash' => '',
                'invalidated_at' => now(),
            ]);
    }
}
