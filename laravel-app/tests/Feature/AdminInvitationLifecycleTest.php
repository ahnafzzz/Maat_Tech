<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminInvitationRequest;
use App\Models\User;
use App\Notifications\AdminInvitationNotification;
use App\Services\AdminSessionVersion;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdminInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Operator-42!';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function admin(array $attributes = []): Admin
    {
        return Admin::create(array_merge([
            'admin_id' => 'ADM-'.fake()->unique()->numerify('####').'-X',
            'name' => 'Existing Administrator',
            'email' => fake()->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'is_lead' => false,
            'status' => 'active',
            'session_version' => Str::random(64),
        ], $attributes));
    }

    private function pending(Admin $requester, array $attributes = []): AdminInvitationRequest
    {
        $email = $attributes['email'] ?? fake()->unique()->safeEmail();

        return AdminInvitationRequest::create(array_merge([
            'requested_by_admin_id' => $requester->id,
            'proposed_admin_id' => 'ADM-'.fake()->unique()->numerify('####').'-N',
            'name' => 'Invited Operator',
            'email' => $email,
            'normalized_email' => strtolower($email),
            'reserved_email' => strtolower($email),
            'status' => AdminInvitationRequest::STATUS_PENDING,
        ], $attributes));
    }

    private function asAdmin(Admin $admin): static
    {
        return $this->actingAs($admin, 'admin')->withSession([
            AdminSessionVersion::SESSION_KEY => $admin->session_version,
        ]);
    }

    private function approve(Admin $lead, AdminInvitationRequest $invitation): string
    {
        $this->asAdmin($lead)->post(route('admin.invitations.approve', $invitation))->assertRedirect();

        return $this->latestSentToken();
    }

    private function latestSentToken(): string
    {
        $token = null;
        Notification::assertSentOnDemand(
            AdminInvitationNotification::class,
            function (AdminInvitationNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );
        $this->assertIsString($token);

        return $token;
    }

    public function test_invitation_management_requires_an_active_authorized_administrator(): void
    {
        $requester = $this->admin();
        $invitation = $this->pending($requester);
        $customer = User::factory()->create();

        $this->post(route('admin.invitations.approve', $invitation))->assertRedirect(route('admin.login'));
        $this->actingAs($customer)->post(route('admin.invitations.reject', $invitation))->assertRedirect(route('admin.login'));

        $inactive = $this->admin(['status' => 'inactive']);
        $this->asAdmin($inactive)->post(route('admin.invitations.approve', $invitation))->assertRedirect(route('admin.login'));

        $this->asAdmin($requester)->post(route('admin.invitations.approve', $invitation))->assertForbidden();
        $this->post(route('admin.invitations.reject', $invitation))->assertForbidden();
        $this->post(route('admin.invitations.resend', $invitation))->assertForbidden();
        $this->post(route('admin.invitations.revoke', $invitation))->assertForbidden();
        $this->assertSame(AdminInvitationRequest::STATUS_PENDING, $invitation->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_active_admin_can_request_normalized_reserved_identity_and_conflicts_are_controlled(): void
    {
        $requester = $this->admin();

        $this->asAdmin($requester)->post(route('admin.invitations.store'), [
            'name' => 'New Operator',
            'email' => '  New.Operator@Example.TEST ',
            'proposed_admin_id' => 'ADM-4100-Q',
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_invitation_requests', [
            'proposed_admin_id' => 'ADM-4100-Q',
            'email' => 'new.operator@example.test',
            'normalized_email' => 'new.operator@example.test',
            'reserved_email' => 'new.operator@example.test',
            'status' => AdminInvitationRequest::STATUS_PENDING,
            'token_hash' => null,
        ]);
        $this->assertDatabaseCount('admins', 1);

        $this->post(route('admin.invitations.store'), [
            'name' => 'Duplicate Operator',
            'email' => 'NEW.OPERATOR@example.test',
            'proposed_admin_id' => 'ADM-4101-Q',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('admin_invitation_requests', 1);
    }

    public function test_lead_approval_only_issues_a_hashed_expiring_non_lead_invitation_after_commit(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester, ['email' => 'operator@example.test']);

        $token = $this->approve($lead, $invitation);
        $invitation->refresh();

        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $invitation->status);
        $this->assertSame(['is_lead' => false], $invitation->approved_permissions);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $invitation->token_selector);
        $this->assertTrue($invitation->token_expires_at->isFuture());
        $this->assertSame('sent', $invitation->delivery_status);
        $this->assertNotNull($invitation->last_sent_at);
        $this->assertDatabaseCount('admins', 2);
        Notification::assertSentOnDemandTimes(AdminInvitationNotification::class, 1);
        Notification::assertSentOnDemand(AdminInvitationNotification::class, function (AdminInvitationNotification $notification) use ($invitation, $token): bool {
            $actionUrl = $notification->toMail(new \stdClass)->actionUrl;

            return $notification->selector === $invitation->token_selector
                && str_contains($actionUrl, '/admin/invitations/accept/'.$invitation->token_selector.'#token='.$token)
                && ! str_contains(strstr($actionUrl, '#', true), $token);
        });
    }

    public function test_rejection_and_competing_or_repeated_transitions_are_controlled(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $rejected = $this->pending($requester);

        $this->asAdmin($lead)->post(route('admin.invitations.reject', $rejected), ['decision_note' => 'Not approved'])
            ->assertRedirect();
        $this->assertDatabaseHas('admin_invitation_requests', [
            'id' => $rejected->id,
            'status' => AdminInvitationRequest::STATUS_REJECTED,
            'decision_note' => 'Not approved',
            'reserved_email' => null,
        ]);
        $this->post(route('admin.invitations.approve', $rejected))->assertSessionHasErrors('invitation');
        $this->post(route('admin.invitations.reject', $rejected))->assertSessionHasErrors('invitation');

        $approved = $this->pending($requester);
        $this->approve($lead, $approved);
        $this->post(route('admin.invitations.approve', $approved))->assertSessionHasErrors('invitation');
        $this->post(route('admin.invitations.reject', $approved))->assertSessionHasErrors('invitation');
        Notification::assertSentOnDemandTimes(AdminInvitationNotification::class, 1);
        $this->assertDatabaseCount('admins', 2);
    }

    public function test_acceptance_get_does_not_consume_and_post_binds_identity_permissions_and_password(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester, [
            'proposed_admin_id' => 'ADM-4200-R',
            'name' => 'Bound Operator',
            'email' => 'bound.operator@example.test',
            'normalized_email' => 'bound.operator@example.test',
            'reserved_email' => 'bound.operator@example.test',
        ]);
        $token = $this->approve($lead, $invitation);
        $storedHash = $invitation->fresh()->token_hash;
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $selector = $invitation->fresh()->token_selector;
        $this->get(route('admin.invitations.accept.show', $selector).'#token='.$token)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('ADM-4200-R')->assertSee('bound.operator@example.test')
            ->assertDontSee($token);
        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $invitation->fresh()->status);
        $this->assertSame($storedHash, $invitation->fresh()->token_hash);

        $this->post(route('admin.invitations.accept', $selector), [
            'token' => $token,
            'email' => 'attacker@example.test',
            'is_lead' => '1',
            'role' => 'lead',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('admin.login'));

        $created = Admin::where('admin_id', 'ADM-4200-R')->sole();
        $this->assertSame('bound.operator@example.test', $created->email);
        $this->assertSame('Bound Operator', $created->name);
        $this->assertFalse($created->is_lead);
        $this->assertTrue($created->isActive());
        $this->assertTrue(Hash::check(self::PASSWORD, $created->password));
        $this->assertSame(AdminInvitationRequest::STATUS_ACCEPTED, $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->token_hash);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->post(route('admin.login.store'), [
            'admin_id' => 'ADM-4200-R',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($created, 'admin');
    }

    public function test_expired_malformed_and_reused_tokens_are_rejected(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $expired = $this->pending($requester);
        $expiredToken = $this->approve($lead, $expired);
        $expiredSelector = $expired->fresh()->token_selector;
        $expired->update(['token_expires_at' => now()->subSecond()]);

        $this->get(route('admin.invitations.accept.show', $expiredSelector))->assertStatus(410);
        $this->post(route('admin.invitations.accept', $expiredSelector), [
            'token' => $expiredToken,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertStatus(410)->assertSee('expired');
        $this->assertSame(AdminInvitationRequest::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertNull($expired->fresh()->token_hash);

        $this->get('/admin/invitations/accept/not-a-token')->assertNotFound();
        $valid = $this->pending($requester);
        $validToken = $this->approve($lead, $valid);
        $validSelector = $valid->fresh()->token_selector;
        $this->post(route('admin.invitations.accept', $validSelector), [
            'token' => 'malformed-secret',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertSee('token');
        $this->assertNull(session()->getOldInput('token'));
        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $valid->fresh()->status);
        $this->post(route('admin.invitations.accept', $validSelector), [
            'token' => $validToken,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('admin.login'));
        $this->post(route('admin.invitations.accept', $validSelector), [
            'token' => $validToken,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertNotFound();
        $this->assertDatabaseCount('admins', 3);
    }

    public function test_resend_supersedes_old_token_and_is_throttled(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester);
        $oldToken = $this->approve($lead, $invitation);
        $oldSelector = $invitation->fresh()->token_selector;

        $this->asAdmin($lead)->post(route('admin.invitations.resend', $invitation))->assertRedirect();
        $newToken = $this->latestSentToken();
        $newSelector = $invitation->fresh()->token_selector;
        $this->assertNotSame($oldToken, $newToken);
        $this->assertNotSame($oldSelector, $newSelector);
        $this->assertSame(hash('sha256', $newToken), $invitation->fresh()->token_hash);
        $this->get(route('admin.invitations.accept.show', $oldSelector))->assertNotFound();
        $this->get(route('admin.invitations.accept.show', $newSelector))->assertOk();

        $this->asAdmin($lead)->post(route('admin.invitations.resend', $invitation))->assertRedirect();
        $this->post(route('admin.invitations.resend', $invitation))->assertRedirect();
        $this->post(route('admin.invitations.resend', $invitation))->assertTooManyRequests();
    }

    public function test_revocation_invalidates_token_and_prevents_acceptance(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester);
        $token = $this->approve($lead, $invitation);
        $selector = $invitation->fresh()->token_selector;

        $this->asAdmin($lead)->post(route('admin.invitations.revoke', $invitation))->assertRedirect();
        $this->assertSame(AdminInvitationRequest::STATUS_REVOKED, $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->token_hash);
        $this->assertNull($invitation->fresh()->reserved_email);
        $this->get(route('admin.invitations.accept.show', $selector))->assertNotFound();
        $this->post(route('admin.invitations.accept', $selector), [
            'token' => $token,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertNotFound();
        $this->assertDatabaseCount('admins', 2);
    }

    public function test_existing_account_conflicts_do_not_overwrite_credentials_or_consume_invitation(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester, ['email' => 'conflict@example.test']);
        $token = $this->approve($lead, $invitation);
        $selector = $invitation->fresh()->token_selector;

        $invitation->update(['approved_permissions' => ['is_lead' => true]]);
        $this->post(route('admin.invitations.accept', $selector), [
            'token' => $token,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertSee('allowed permission set');
        $this->assertDatabaseMissing('admins', ['email' => 'conflict@example.test']);
        $invitation->update(['approved_permissions' => ['is_lead' => false]]);

        $existing = $this->admin(['email' => 'conflict@example.test', 'password' => 'Existing-Password-42!']);

        $this->post(route('admin.invitations.accept', $selector), [
            'token' => $token,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertSee('conflicts with an existing account');

        $this->assertTrue(Hash::check('Existing-Password-42!', $existing->fresh()->password));
        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $invitation->fresh()->status);
        $this->assertNotNull($invitation->fresh()->token_hash);
        $this->assertDatabaseCount('admins', 3);
    }

    public function test_account_creation_failure_rolls_back_invitation_consumption(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester, ['proposed_admin_id' => 'ADM-4300-F']);
        $token = $this->approve($lead, $invitation);
        $selector = $invitation->fresh()->token_selector;
        $storedHash = $invitation->fresh()->token_hash;
        DB::statement("CREATE TRIGGER reject_invited_admin BEFORE INSERT ON admins WHEN NEW.admin_id = 'ADM-4300-F' BEGIN SELECT RAISE(ABORT, 'injected account creation failure'); END");

        $this->post(route('admin.invitations.accept', $selector), [
            'token' => $token,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertSee('could not be created');

        $this->assertDatabaseMissing('admins', ['admin_id' => 'ADM-4300-F']);
        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $invitation->fresh()->status);
        $this->assertSame($storedHash, $invitation->fresh()->token_hash);
    }

    public function test_delivery_failure_is_recoverable_and_never_rolls_back_approval(): void
    {
        $requester = $this->admin();
        $lead = $this->admin(['is_lead' => true]);
        $invitation = $this->pending($requester);
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

        $this->asAdmin($lead)->post(route('admin.invitations.approve', $invitation))
            ->assertRedirect()->assertSessionHasErrors('invitation');

        $this->assertSame(AdminInvitationRequest::STATUS_APPROVED, $invitation->fresh()->status);
        $this->assertSame('failed', $invitation->fresh()->delivery_status);
        $this->assertNotNull($invitation->fresh()->token_hash);
        $this->assertDatabaseCount('admins', 2);
    }

    public function test_legacy_shaped_pending_record_remains_inactive_without_a_usable_token(): void
    {
        $requester = $this->admin();
        DB::table('admin_invitation_requests')->insert([
            'requested_by_admin_id' => $requester->id,
            'proposed_admin_id' => 'ADM-4400-L',
            'name' => 'Legacy Pending',
            'email' => 'legacy@example.test',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legacy = AdminInvitationRequest::where('proposed_admin_id', 'ADM-4400-L')->sole();
        $this->assertSame(AdminInvitationRequest::STATUS_PENDING, $legacy->status);
        $this->assertNull($legacy->token_hash);
        $this->assertNull($legacy->accepted_at);
        $this->assertDatabaseMissing('admins', ['admin_id' => 'ADM-4400-L']);
    }
}
