<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminInvitationRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminInvitationService
{
    public function request(Admin $requester, array $identity): AdminInvitationRequest
    {
        $normalizedEmail = $this->normalizeEmail($identity['email']);

        try {
            return DB::transaction(function () use ($requester, $identity, $normalizedEmail): AdminInvitationRequest {
                $lockedRequester = Admin::whereKey($requester->id)->lockForUpdate()->first();
                if (! $lockedRequester?->isActive()) {
                    throw new AuthorizationException;
                }

                if (Admin::where('admin_id', $identity['proposed_admin_id'])
                    ->orWhereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
                    throw ValidationException::withMessages(['email' => 'That administrator identity is already in use.']);
                }

                if (AdminInvitationRequest::where('proposed_admin_id', $identity['proposed_admin_id'])->exists()) {
                    throw ValidationException::withMessages(['proposed_admin_id' => 'That administrator ID has already been requested.']);
                }

                if (AdminInvitationRequest::where('reserved_email', $normalizedEmail)->exists()) {
                    throw ValidationException::withMessages(['email' => 'An invitation for that email is already pending completion.']);
                }

                return AdminInvitationRequest::create([
                    'requested_by_admin_id' => $lockedRequester->id,
                    'proposed_admin_id' => $identity['proposed_admin_id'],
                    'name' => $identity['name'],
                    'email' => $normalizedEmail,
                    'normalized_email' => $normalizedEmail,
                    'reserved_email' => $normalizedEmail,
                    'status' => AdminInvitationRequest::STATUS_PENDING,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['email' => 'That administrator identity is already reserved.']);
        }
    }

    public function approve(Admin $lead, int $invitationId): array
    {
        return DB::transaction(function () use ($lead, $invitationId): array {
            $this->authorizedLead($lead);
            $invitation = AdminInvitationRequest::whereKey($invitationId)->lockForUpdate()->firstOrFail();
            $this->requireStatus($invitation, [AdminInvitationRequest::STATUS_PENDING]);
            $this->ensureIdentityAvailable($invitation);

            return $this->issueToken($invitation, [
                'status' => AdminInvitationRequest::STATUS_APPROVED,
                'approved_permissions' => ['is_lead' => false],
                'reviewed_by_admin_id' => $lead->id,
                'reviewed_at' => now(),
                'decision_note' => null,
            ]);
        }, 3);
    }

    public function reject(Admin $lead, int $invitationId, ?string $decisionNote): AdminInvitationRequest
    {
        return DB::transaction(function () use ($lead, $invitationId, $decisionNote): AdminInvitationRequest {
            $this->authorizedLead($lead);
            $invitation = AdminInvitationRequest::whereKey($invitationId)->lockForUpdate()->firstOrFail();
            $this->requireStatus($invitation, [AdminInvitationRequest::STATUS_PENDING]);

            $invitation->forceFill([
                'status' => AdminInvitationRequest::STATUS_REJECTED,
                'reserved_email' => null,
                'decision_note' => $decisionNote,
                'reviewed_by_admin_id' => $lead->id,
                'reviewed_at' => now(),
            ])->save();

            return $invitation;
        }, 3);
    }

    public function resend(Admin $lead, int $invitationId): array
    {
        return DB::transaction(function () use ($lead, $invitationId): array {
            $this->authorizedLead($lead);
            $invitation = AdminInvitationRequest::whereKey($invitationId)->lockForUpdate()->firstOrFail();
            $this->requireStatus($invitation, [
                AdminInvitationRequest::STATUS_APPROVED,
                AdminInvitationRequest::STATUS_EXPIRED,
            ]);
            $this->ensureIdentityAvailable($invitation);

            return $this->issueToken($invitation, [
                'status' => AdminInvitationRequest::STATUS_APPROVED,
            ]);
        }, 3);
    }

    public function revoke(Admin $lead, int $invitationId): AdminInvitationRequest
    {
        return DB::transaction(function () use ($lead, $invitationId): AdminInvitationRequest {
            $this->authorizedLead($lead);
            $invitation = AdminInvitationRequest::whereKey($invitationId)->lockForUpdate()->firstOrFail();
            $this->requireStatus($invitation, [
                AdminInvitationRequest::STATUS_APPROVED,
                AdminInvitationRequest::STATUS_EXPIRED,
            ]);

            $invitation->forceFill([
                'status' => AdminInvitationRequest::STATUS_REVOKED,
                'reserved_email' => null,
                'token_selector' => null,
                'token_hash' => null,
                'token_expires_at' => null,
                'delivery_status' => 'revoked',
                'revoked_at' => now(),
            ])->save();

            return $invitation;
        }, 3);
    }

    public function findAcceptable(string $selector): ?AdminInvitationRequest
    {
        if (! $this->selectorIsWellFormed($selector)) {
            return null;
        }

        return AdminInvitationRequest::where('token_selector', $selector)
            ->where('status', AdminInvitationRequest::STATUS_APPROVED)
            ->first();
    }

    public function accept(string $selector, string $token, string $password): Admin
    {
        if (! $this->selectorIsWellFormed($selector) || ! $this->tokenIsWellFormed($token)) {
            throw ValidationException::withMessages(['invitation' => 'This invitation link is invalid or no longer available.']);
        }

        $result = DB::transaction(function () use ($selector, $token, $password): array {
            $invitation = AdminInvitationRequest::where('token_selector', $selector)
                ->where('token_hash', $this->hashToken($token))
                ->lockForUpdate()->first();

            if (! $invitation || $invitation->status !== AdminInvitationRequest::STATUS_APPROVED) {
                return ['error' => 'This invitation link is invalid or no longer available.'];
            }

            if (! $invitation->token_expires_at || now()->greaterThanOrEqualTo($invitation->token_expires_at)) {
                $invitation->forceFill([
                    'status' => AdminInvitationRequest::STATUS_EXPIRED,
                    'token_selector' => null,
                    'token_hash' => null,
                    'token_expires_at' => null,
                    'delivery_status' => 'expired',
                ])->save();

                return ['error' => 'This invitation has expired. Ask a lead administrator to resend it.'];
            }

            if ($this->identityExists($invitation)) {
                return ['error' => 'This administrator identity conflicts with an existing account.'];
            }

            if ($invitation->approved_permissions !== ['is_lead' => false]) {
                return ['error' => 'This invitation does not contain an allowed permission set.'];
            }

            $admin = Admin::create([
                'admin_id' => $invitation->proposed_admin_id,
                'name' => $invitation->name,
                'email' => $invitation->normalized_email,
                'password' => Hash::make($password),
                'is_lead' => false,
                'status' => 'active',
                'session_version' => Str::random(64),
            ]);

            $invitation->forceFill([
                'status' => AdminInvitationRequest::STATUS_ACCEPTED,
                'reserved_email' => null,
                'token_selector' => null,
                'token_hash' => null,
                'token_expires_at' => null,
                'delivery_status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            return ['admin' => $admin];
        }, 3);

        if (isset($result['error'])) {
            throw ValidationException::withMessages(['invitation' => $result['error']]);
        }

        return $result['admin'];
    }

    public function markDelivered(int $invitationId, string $selector, string $token): void
    {
        AdminInvitationRequest::whereKey($invitationId)
            ->where('token_selector', $selector)
            ->where('token_hash', $this->hashToken($token))
            ->where('status', AdminInvitationRequest::STATUS_APPROVED)
            ->update(['delivery_status' => 'sent', 'last_sent_at' => now()]);
    }

    public function markDeliveryFailed(int $invitationId, string $selector, string $token): void
    {
        AdminInvitationRequest::whereKey($invitationId)
            ->where('token_selector', $selector)
            ->where('token_hash', $this->hashToken($token))
            ->where('status', AdminInvitationRequest::STATUS_APPROVED)
            ->update(['delivery_status' => 'failed']);
    }

    private function issueToken(AdminInvitationRequest $invitation, array $attributes): array
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $invitation->forceFill($attributes + [
            'token_selector' => $selector,
            'token_hash' => $this->hashToken($token),
            'token_expires_at' => now()->addMinutes(max(1, (int) config('admin.invitation_expiration_minutes', 60))),
            'delivery_status' => 'pending',
            'last_sent_at' => null,
            'accepted_at' => null,
            'revoked_at' => null,
        ])->save();

        return ['invitation' => $invitation, 'selector' => $selector, 'token' => $token];
    }

    private function authorizedLead(Admin $lead): Admin
    {
        $lockedLead = Admin::whereKey($lead->id)->lockForUpdate()->first();
        if (! $lockedLead?->isActive() || ! $lockedLead->is_lead) {
            throw new AuthorizationException;
        }

        return $lockedLead;
    }

    private function requireStatus(AdminInvitationRequest $invitation, array $allowed): void
    {
        if (! in_array($invitation->status, $allowed, true)) {
            throw ValidationException::withMessages(['invitation' => 'That invitation transition is no longer available.']);
        }
    }

    private function ensureIdentityAvailable(AdminInvitationRequest $invitation): void
    {
        if ($this->identityExists($invitation)) {
            throw ValidationException::withMessages(['invitation' => 'That administrator identity conflicts with an existing account.']);
        }
    }

    private function identityExists(AdminInvitationRequest $invitation): bool
    {
        return Admin::where('admin_id', $invitation->proposed_admin_id)
            ->orWhereRaw('LOWER(email) = ?', [$invitation->normalized_email])
            ->exists();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function tokenIsWellFormed(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
    }

    private function selectorIsWellFormed(string $selector): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/', $selector) === 1;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
