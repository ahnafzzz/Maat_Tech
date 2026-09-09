<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_invitation_requests', function (Blueprint $table) {
            $table->string('normalized_email')->nullable()->after('email')->index();
            $table->string('reserved_email')->nullable()->after('normalized_email')->unique();
            $table->json('approved_permissions')->nullable()->after('status');
            $table->string('token_selector', 32)->nullable()->after('approved_permissions')->unique();
            $table->string('token_hash', 64)->nullable()->after('token_selector')->unique();
            $table->timestamp('token_expires_at')->nullable()->after('token_hash')->index();
            $table->string('delivery_status')->default('not_sent')->after('token_expires_at');
            $table->timestamp('last_sent_at')->nullable()->after('delivery_status');
            $table->timestamp('accepted_at')->nullable()->after('last_sent_at');
            $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            $table->index(['status', 'token_expires_at'], 'admin_invitations_status_expiry_index');
        });

        $reservedEmails = [];

        DB::table('admin_invitation_requests')->orderBy('id')->get()->each(function (object $invitation) use (&$reservedEmails): void {
            $normalizedEmail = mb_strtolower(trim((string) $invitation->email));
            $updates = ['normalized_email' => $normalizedEmail];

            if ($invitation->status === 'pending') {
                $identityTaken = DB::table('admins')
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->orWhere('admin_id', $invitation->proposed_admin_id)
                    ->exists();

                if ($identityTaken || isset($reservedEmails[$normalizedEmail])) {
                    $updates += [
                        'status' => 'revoked',
                        'decision_note' => 'Legacy request requires a new invitation because its identity is no longer uniquely reservable.',
                        'revoked_at' => now(),
                    ];
                } else {
                    $updates['reserved_email'] = $normalizedEmail;
                    $reservedEmails[$normalizedEmail] = true;
                }
            } elseif ($invitation->status === 'approved') {
                $existingAdmin = DB::table('admins')
                    ->where('admin_id', $invitation->proposed_admin_id)
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->exists();

                $updates += $existingAdmin
                    ? ['status' => 'accepted', 'accepted_at' => $invitation->reviewed_at ?? now()]
                    : ['status' => 'expired'];
            }

            DB::table('admin_invitation_requests')->where('id', $invitation->id)->update($updates);
        });
    }

    public function down(): void
    {
        Schema::table('admin_invitation_requests', function (Blueprint $table) {
            $table->dropIndex('admin_invitations_status_expiry_index');
            $table->dropUnique(['reserved_email']);
            $table->dropUnique(['token_selector']);
            $table->dropUnique(['token_hash']);
            $table->dropIndex(['normalized_email']);
            $table->dropIndex(['token_expires_at']);
            $table->dropColumn([
                'normalized_email',
                'reserved_email',
                'approved_permissions',
                'token_selector',
                'token_hash',
                'token_expires_at',
                'delivery_status',
                'last_sent_at',
                'accepted_at',
                'revoked_at',
            ]);
        });
    }
};
