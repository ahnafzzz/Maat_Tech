<?php

namespace Tests\Feature;

use App\Models\AdminInvitationRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminInvitationMigrationTest extends TestCase
{
    public function test_legacy_invitations_are_reconciled_without_creating_or_activating_accounts(): void
    {
        $originalConnection = config('database.default');
        $databasePath = tempnam(sys_get_temp_dir(), 'maat-invitation-migration-');
        $this->assertNotFalse($databasePath);

        config([
            'database.connections.invitation_legacy' => [
                ...config('database.connections.sqlite'),
                'database' => $databasePath,
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'invitation_legacy',
        ]);
        DB::purge('invitation_legacy');

        try {
            $schema = DB::connection('invitation_legacy')->getSchemaBuilder();
            $schema->create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('admin_id')->unique();
                $table->string('email')->unique();
            });
            $schema->create('admin_invitation_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('requested_by_admin_id');
                $table->string('proposed_admin_id')->unique();
                $table->string('name');
                $table->string('email');
                $table->string('status')->default('pending');
                $table->text('decision_note')->nullable();
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });

            DB::table('admins')->insert([
                'id' => 1,
                'admin_id' => 'ADM-4500-E',
                'email' => 'existing@example.test',
            ]);
            DB::table('admin_invitation_requests')->insert([
                [
                    'requested_by_admin_id' => 1,
                    'proposed_admin_id' => 'ADM-4501-L',
                    'name' => 'Safe Legacy Pending',
                    'email' => ' PENDING@Example.TEST ',
                    'status' => 'pending',
                    'reviewed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'requested_by_admin_id' => 1,
                    'proposed_admin_id' => 'ADM-4502-L',
                    'name' => 'Duplicate Legacy Pending',
                    'email' => 'pending@example.test',
                    'status' => 'pending',
                    'reviewed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'requested_by_admin_id' => 1,
                    'proposed_admin_id' => 'ADM-4503-L',
                    'name' => 'Conflicting Legacy Pending',
                    'email' => 'existing@example.test',
                    'status' => 'pending',
                    'reviewed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'requested_by_admin_id' => 1,
                    'proposed_admin_id' => 'ADM-4500-E',
                    'name' => 'Previously Created Admin',
                    'email' => 'existing@example.test',
                    'status' => 'approved',
                    'reviewed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'requested_by_admin_id' => 1,
                    'proposed_admin_id' => 'ADM-4504-L',
                    'name' => 'Incomplete Legacy Approval',
                    'email' => 'incomplete@example.test',
                    'status' => 'approved',
                    'reviewed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $migration = require database_path('migrations/2026_09_09_000000_secure_admin_invitation_lifecycle.php');
            $migration->up();

            $this->assertDatabaseHas('admin_invitation_requests', [
                'proposed_admin_id' => 'ADM-4501-L',
                'normalized_email' => 'pending@example.test',
                'reserved_email' => 'pending@example.test',
                'status' => AdminInvitationRequest::STATUS_PENDING,
                'token_hash' => null,
            ], 'invitation_legacy');
            $this->assertDatabaseHas('admin_invitation_requests', [
                'proposed_admin_id' => 'ADM-4502-L',
                'status' => AdminInvitationRequest::STATUS_REVOKED,
                'token_hash' => null,
            ], 'invitation_legacy');
            $this->assertDatabaseHas('admin_invitation_requests', [
                'proposed_admin_id' => 'ADM-4503-L',
                'status' => AdminInvitationRequest::STATUS_REVOKED,
            ], 'invitation_legacy');
            $this->assertDatabaseHas('admin_invitation_requests', [
                'proposed_admin_id' => 'ADM-4500-E',
                'status' => AdminInvitationRequest::STATUS_ACCEPTED,
                'token_hash' => null,
            ], 'invitation_legacy');
            $this->assertDatabaseHas('admin_invitation_requests', [
                'proposed_admin_id' => 'ADM-4504-L',
                'status' => AdminInvitationRequest::STATUS_EXPIRED,
                'token_hash' => null,
            ], 'invitation_legacy');
            $this->assertSame(1, DB::table('admins')->count());
        } finally {
            DB::disconnect('invitation_legacy');
            DB::purge('invitation_legacy');
            config(['database.default' => $originalConnection]);
            if (is_string($databasePath) && is_file($databasePath)) {
                unlink($databasePath);
            }
        }
    }
}
