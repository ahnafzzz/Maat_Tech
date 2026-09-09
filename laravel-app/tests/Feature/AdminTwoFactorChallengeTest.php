<?php

namespace Tests\Feature;

use App\Console\Commands\RotateAdministratorPassword;
use App\Models\Admin;
use App\Models\AdminTwoFactorChallenge;
use App\Notifications\AdminTwoFactorCodeNotification;
use App\Services\AdminTwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class AdminTwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Admin-42!';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function admin(array $attributes = []): Admin
    {
        return Admin::create(array_merge([
            'admin_id' => 'ADM-'.fake()->unique()->numerify('####').'-T',
            'name' => 'Two Factor Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'status' => 'active',
            'two_factor_enabled' => true,
            'session_version' => Str::random(64),
        ], $attributes));
    }

    private function begin(Admin $admin): array
    {
        $this->post(route('admin.login.store'), [
            'admin_id' => $admin->admin_id,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.two-factor.challenge'));

        $notification = Notification::sent($admin, AdminTwoFactorCodeNotification::class)->last();
        $this->assertInstanceOf(AdminTwoFactorCodeNotification::class, $notification);
        $challenge = AdminTwoFactorChallenge::where('admin_id', $admin->id)->latest('id')->firstOrFail();

        return [$challenge, $notification->code(), session(AdminTwoFactorService::PENDING_SELECTOR_KEY), session(AdminTwoFactorService::PENDING_BINDING_KEY)];
    }

    public function test_password_login_creates_a_hashed_browser_bound_challenge_without_authenticating(): void
    {
        $admin = $this->admin();
        $oldSessionId = session()->getId();
        [$challenge, $code, $selector, $binding] = $this->begin($admin);

        $this->assertGuest('admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame($challenge->selector, $selector);
        $this->assertTrue(Hash::check($code, $challenge->code_hash));
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertSame(hash('sha256', $binding), $challenge->session_binding_hash);
        $this->assertNotNull($challenge->delivered_at);
        $this->get(route('admin.two-factor.challenge'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_valid_challenge_is_single_use_and_establishes_browser_and_api_access(): void
    {
        $admin = $this->admin();
        [$challenge, $code, $selector, $binding] = $this->begin($admin);
        $pendingSessionId = session()->getId();

        $this->post(route('admin.two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotSame($pendingSessionId, session()->getId());
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(AdminTwoFactorChallenge::STATUS_CONSUMED, $challenge->fresh()->status);
        $this->assertNull(session(AdminTwoFactorService::PENDING_SELECTOR_KEY));
        $this->assertSame('missing', app(AdminTwoFactorService::class)->verify($selector, $binding.'tampered', $code)['status']);
        $this->assertSame(AdminTwoFactorChallenge::STATUS_CONSUMED, app(AdminTwoFactorService::class)->verify($selector, $binding, $code)['status']);
    }

    public function test_malformed_and_incorrect_submissions_count_toward_exhaustion_and_clear_pending_state(): void
    {
        $admin = $this->admin();
        [$challenge] = $this->begin($admin);

        foreach ([null, 'abc', '12345', '000000', '999999'] as $index => $code) {
            $response = $this->post(route('admin.two-factor.verify'), ['code' => $code]);
            if ($index < 4) {
                $response->assertRedirect()->assertSessionHasErrors('code');
            } else {
                $response->assertRedirect(route('admin.login'))->assertSessionHasErrors('admin_id');
            }
        }

        $this->assertSame(5, $challenge->fresh()->failed_attempts);
        $this->assertSame(AdminTwoFactorChallenge::STATUS_EXHAUSTED, $challenge->fresh()->status);
        $this->assertGuest('admin');
        $this->assertNull(session(AdminTwoFactorService::PENDING_SELECTOR_KEY));
    }

    public function test_expired_challenge_has_a_recovery_path_and_cannot_be_replayed(): void
    {
        $admin = $this->admin();
        [$challenge, $code] = $this->begin($admin);
        $challenge->update(['expires_at' => now()->subSecond()]);

        $this->post(route('admin.two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('admin.login'))->assertSessionHasErrors('admin_id');

        $this->assertSame(AdminTwoFactorChallenge::STATUS_EXPIRED, $challenge->fresh()->status);
        $this->assertGuest('admin');
    }

    public function test_separate_browser_challenges_remain_independent_and_binding_cannot_be_swapped(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactorService::class);
        $first = $service->createChallenge($admin, self::PASSWORD, null, null);
        $service->markDelivered($first['challenge']);
        $second = $service->createChallenge($admin, self::PASSWORD, null, null);
        $service->markDelivered($second['challenge']);

        $this->assertSame('missing', $service->verify($first['challenge']->selector, $second['binding'], $first['code'])['status']);
        $this->assertSame(0, $first['challenge']->fresh()->failed_attempts);
        $this->assertSame('verified', $service->verify($first['challenge']->selector, $first['binding'], $first['code'])['status']);
        $this->assertSame(AdminTwoFactorChallenge::STATUS_PENDING, $second['challenge']->fresh()->status);
        $this->assertSame('verified', $service->verify($second['challenge']->selector, $second['binding'], $second['code'])['status']);
    }

    public function test_same_browser_reauthentication_supersedes_only_its_previous_challenge(): void
    {
        $admin = $this->admin();
        [$first] = $this->begin($admin);
        $this->post(route('admin.login.store'), [
            'admin_id' => $admin->admin_id,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.two-factor.challenge'));

        $this->assertSame(AdminTwoFactorChallenge::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(AdminTwoFactorChallenge::STATUS_PENDING, AdminTwoFactorChallenge::latest('id')->first()->status);
    }

    public function test_successful_non_two_factor_login_clears_an_old_pending_browser_state(): void
    {
        [$challenge] = $this->begin($this->admin());
        $withoutTwoFactor = $this->admin(['two_factor_enabled' => false]);

        $this->post(route('admin.login.store'), [
            'admin_id' => $withoutTwoFactor->admin_id,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($withoutTwoFactor, 'admin');
        $this->assertNull(session(AdminTwoFactorService::PENDING_SELECTOR_KEY));
        $this->assertSame(AdminTwoFactorChallenge::STATUS_SUPERSEDED, $challenge->fresh()->status);
    }

    public function test_password_rotation_and_account_deactivation_invalidate_pending_authentication(): void
    {
        $admin = $this->admin();
        [$challenge, $code] = $this->begin($admin);
        $command = new RotateAdministratorPassword;
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->setInputs(['Rotated-Admin-84!', 'Rotated-Admin-84!']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['admin_id' => $admin->admin_id]));

        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.login'));
        $this->assertSame(AdminTwoFactorChallenge::STATUS_INVALIDATED, $challenge->fresh()->status);
        $this->assertGuest('admin');

        $inactive = $this->admin();
        [, $inactiveCode] = $this->begin($inactive);
        $inactive->update(['status' => 'inactive']);
        $this->post(route('admin.two-factor.verify'), ['code' => $inactiveCode])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_account_creation_limit_cannot_be_reset_by_a_new_browser(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactorService::class);

        for ($i = 0; $i < 5; $i++) {
            $created = $service->createChallenge($admin, self::PASSWORD, null, null);
            $this->assertSame('created', $created['status']);
        }

        $this->assertSame('rate_limited', $service->createChallenge($admin, self::PASSWORD, null, null)['status']);
        $this->assertDatabaseCount('admin_two_factor_challenges', 5);
    }

    public function test_database_and_delivery_failures_never_authenticate_or_leave_a_usable_pending_session(): void
    {
        $admin = $this->admin();
        DB::statement('CREATE TRIGGER reject_two_factor_challenge BEFORE INSERT ON admin_two_factor_challenges BEGIN SELECT RAISE(ABORT, \'injected challenge failure\'); END');
        $this->post(route('admin.login.store'), ['admin_id' => $admin->admin_id, 'password' => self::PASSWORD])
            ->assertRedirect()->assertSessionHasErrors('admin_id');
        Notification::assertNothingSent();
        $this->assertGuest('admin');
        DB::statement('DROP TRIGGER reject_two_factor_challenge');

        $this->app->instance(Dispatcher::class, new class implements Dispatcher
        {
            public function send($notifiables, $notification): void
            {
                throw new RuntimeException('isolated mail failure');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new RuntimeException('isolated mail failure');
            }
        });
        $this->post(route('admin.login.store'), ['admin_id' => $admin->admin_id, 'password' => self::PASSWORD])
            ->assertRedirect()->assertSessionHasErrors('admin_id');
        $this->assertSame(AdminTwoFactorChallenge::STATUS_DELIVERY_FAILED, AdminTwoFactorChallenge::latest('id')->first()->status);
        $this->assertNull(session(AdminTwoFactorService::PENDING_SELECTOR_KEY));
        $this->assertGuest('admin');
    }

    public function test_legacy_row_codes_are_never_accepted_by_the_new_flow(): void
    {
        $admin = $this->admin([
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['pending_admin_id' => $admin->id])
            ->post(route('admin.two-factor.verify'), ['code' => '123456'])
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertDatabaseCount('admin_two_factor_challenges', 0);
    }

    public function test_additive_migration_invalidates_legacy_codes_without_changing_the_account(): void
    {
        $admin = $this->admin([
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);
        $passwordHash = $admin->password;
        $sessionVersion = $admin->session_version;
        Schema::drop('admin_two_factor_challenges');

        $migration = require database_path('migrations/2026_09_09_010000_create_admin_two_factor_challenges_table.php');
        $migration->up();

        $admin = $admin->fresh();
        $this->assertNull($admin->two_factor_code);
        $this->assertNull($admin->two_factor_expires_at);
        $this->assertTrue($admin->two_factor_enabled);
        $this->assertTrue($admin->isActive());
        $this->assertSame($passwordHash, $admin->password);
        $this->assertSame($sessionVersion, $admin->session_version);
        $this->assertTrue(Schema::hasTable('admin_two_factor_challenges'));
    }
}
