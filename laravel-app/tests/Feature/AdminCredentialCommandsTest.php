<?php

namespace Tests\Feature;

use App\Console\Commands\BootstrapLeadAdministrator;
use App\Console\Commands\RotateAdministratorPassword;
use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AdminCredentialCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_seeding_creates_catalog_but_no_customer_or_administrator_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('admins', 0);
        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseCount('products', 3);
    }

    public function test_valid_first_administrator_bootstrap_hashes_the_hidden_password(): void
    {
        $password = 'Valid-Bootstrap-42!';
        $tester = $this->commandTester(BootstrapLeadAdministrator::class, [$password, $password]);

        $exitCode = $tester->execute([
            '--admin-id' => 'ADM-4100-A',
            '--name' => 'First Lead',
            '--email' => 'first.lead@example.test',
        ]);

        $admin = Admin::sole();
        $this->assertSame(0, $exitCode);
        $this->assertTrue($admin->is_lead);
        $this->assertSame('active', $admin->status);
        $this->assertNotSame($password, $admin->password);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNotEmpty($admin->session_version);
        $this->assertStringNotContainsString($password, $tester->getDisplay());
    }

    public function test_invalid_identity_and_password_confirmation_failure_create_no_account(): void
    {
        $invalidIdentity = $this->commandTester(BootstrapLeadAdministrator::class);
        $this->assertSame(1, $invalidIdentity->execute([
            '--admin-id' => 'invalid',
            '--name' => 'First Lead',
            '--email' => 'not-an-email',
        ]));
        $this->assertDatabaseCount('admins', 0);

        $password = 'Valid-Bootstrap-42!';
        $confirmation = 'Different-Bootstrap-43!';
        $mismatch = $this->commandTester(BootstrapLeadAdministrator::class, [$password, $confirmation]);
        $this->assertSame(1, $mismatch->execute([
            '--admin-id' => 'ADM-4100-A',
            '--name' => 'First Lead',
            '--email' => 'first.lead@example.test',
        ]));
        $this->assertDatabaseCount('admins', 0);
        $this->assertStringNotContainsString($password, $mismatch->getDisplay());
        $this->assertStringNotContainsString($confirmation, $mismatch->getDisplay());
    }

    public function test_existing_lead_administrator_prevents_repeat_bootstrap(): void
    {
        $this->admin(['is_lead' => true]);
        $tester = $this->commandTester(BootstrapLeadAdministrator::class);

        $this->assertSame(1, $tester->execute([
            '--admin-id' => 'ADM-4101-B',
            '--name' => 'Second Lead',
            '--email' => 'second.lead@example.test',
        ]));
        $this->assertDatabaseCount('admins', 1);
    }

    public function test_duplicate_id_or_email_never_overwrites_an_administrator(): void
    {
        $original = $this->admin();
        $originalPassword = $original->password;

        $duplicateId = $this->commandTester(BootstrapLeadAdministrator::class);
        $this->assertSame(1, $duplicateId->execute([
            '--admin-id' => $original->admin_id,
            '--name' => 'Replacement',
            '--email' => 'replacement@example.test',
        ]));

        $duplicateEmail = $this->commandTester(BootstrapLeadAdministrator::class);
        $this->assertSame(1, $duplicateEmail->execute([
            '--admin-id' => 'ADM-4102-C',
            '--name' => 'Replacement',
            '--email' => $original->email,
        ]));

        $original->refresh();
        $this->assertDatabaseCount('admins', 1);
        $this->assertSame('Existing Admin', $original->name);
        $this->assertSame($originalPassword, $original->password);
        $this->assertFalse($original->is_lead);
    }

    public function test_password_rotation_only_changes_selected_credentials_and_invalidates_its_sessions(): void
    {
        $oldPassword = 'Original-Admin-42!';
        $newPassword = 'Replacement-Admin-84!';
        $selected = $this->admin([
            'password' => Hash::make($oldPassword),
            'is_lead' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);
        $other = $this->admin([
            'admin_id' => 'ADM-4201-B',
            'email' => 'other.admin@example.test',
            'name' => 'Other Admin',
        ]);
        $otherSnapshot = $other->refresh()->getAttributes();
        $oldRememberToken = $selected->remember_token;
        $oldSessionVersion = $selected->session_version;

        $this->post('/admin/login', [
            'admin_id' => $selected->admin_id,
            'password' => $oldPassword,
        ])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk();

        $tester = $this->commandTester(RotateAdministratorPassword::class, [$newPassword, $newPassword]);
        $exitCode = $tester->execute(['admin_id' => $selected->admin_id]);

        $selected->refresh();
        $other->refresh();
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("Target: {$selected->admin_id} | {$selected->name} | {$selected->email}", $tester->getDisplay());
        $this->assertStringNotContainsString($oldPassword, $tester->getDisplay());
        $this->assertStringNotContainsString($newPassword, $tester->getDisplay());
        $this->assertFalse(Hash::check($oldPassword, $selected->password));
        $this->assertTrue(Hash::check($newPassword, $selected->password));
        $this->assertNull($selected->two_factor_code);
        $this->assertNull($selected->two_factor_expires_at);
        $this->assertNotSame($oldRememberToken, $selected->remember_token);
        $this->assertNotSame($oldSessionVersion, $selected->session_version);
        $this->assertSame($otherSnapshot, $other->getAttributes());
        $this->assertTrue($selected->is_lead);
        $this->assertSame('active', $selected->status);
        $this->assertSame('existing.admin@example.test', $selected->email);

        Auth::forgetGuards();
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        $this->post('/admin/login', [
            'admin_id' => $selected->admin_id,
            'password' => $oldPassword,
        ])->assertSessionHasErrors('admin_id');
        $this->assertGuest('admin');

        $this->post('/admin/login', [
            'admin_id' => $selected->admin_id,
            'password' => $newPassword,
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($selected, 'admin');
    }

    public function test_password_rotation_confirmation_failure_changes_nothing(): void
    {
        $admin = $this->admin();
        $snapshot = $admin->refresh()->getAttributes();
        $password = 'Replacement-Admin-84!';
        $confirmation = 'Different-Admin-85!';
        $tester = $this->commandTester(RotateAdministratorPassword::class, [$password, $confirmation]);

        $this->assertSame(1, $tester->execute(['admin_id' => $admin->admin_id]));
        $this->assertSame($snapshot, $admin->fresh()->getAttributes());
        $this->assertStringNotContainsString($password, $tester->getDisplay());
        $this->assertStringNotContainsString($confirmation, $tester->getDisplay());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function admin(array $attributes = []): Admin
    {
        return Admin::create(array_merge([
            'admin_id' => 'ADM-4200-A',
            'name' => 'Existing Admin',
            'email' => 'existing.admin@example.test',
            'password' => Hash::make('Existing-Admin-42!'),
            'is_lead' => false,
            'status' => 'active',
            'remember_token' => Str::random(60),
            'session_version' => Str::random(64),
        ], $attributes));
    }

    /**
     * @param  class-string<Command>  $command
     * @param  list<string>  $inputs
     */
    private function commandTester(string $command, array $inputs = []): CommandTester
    {
        $commandInstance = $this->app->make($command);
        $commandInstance->setLaravel($this->app);
        $tester = new CommandTester($commandInstance);
        $tester->setInputs($inputs);

        return $tester;
    }
}
