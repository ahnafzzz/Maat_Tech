<?php

namespace Tests\Feature;

use App\Console\Commands\RotateAdministratorPassword;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminSessionVersion;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slug = 'existing'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'lighting'], ['name' => 'Lighting']);

        return Product::create(['category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'price' => 100, 'stock' => 10]);
    }

    private function admin(bool $active): Admin
    {
        return Admin::create(['admin_id' => 'ADM-1234-A', 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'test-password', 'status' => $active ? 'active' : 'inactive', 'session_version' => Str::random(64)]);
    }

    private function authenticateAs(string $actor): void
    {
        if (in_array($actor, ['customer', 'both-active', 'both-inactive'])) {
            $this->actingAs(User::factory()->create(), 'web');
        }
        if (in_array($actor, ['active', 'inactive', 'both-active', 'both-inactive'])) {
            $admin = $this->admin(in_array($actor, ['active', 'both-active']));
            $this->actingAs($admin, 'admin')->withSession([AdminSessionVersion::SESSION_KEY => $admin->session_version]);
        }
    }

    public static function deniedMutations(): array
    {
        $cases = [];
        foreach (['guest' => 401, 'customer' => 403, 'inactive' => 403, 'both-inactive' => 403] as $actor => $status) {
            foreach (['POST', 'PUT', 'DELETE'] as $method) {
                $cases["$actor $method"] = [$actor, $method, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('deniedMutations')]
    public function test_product_mutations_deny_unauthorized_actors_without_changes(string $actor, string $method, int $status): void
    {
        $product = $this->product();
        $this->authenticateAs($actor);
        $before = Product::all()->toArray();
        $payload = ['category_id' => $product->category_id, 'name' => 'New product', 'slug' => "new-$actor-".strtolower($method), 'price' => 250, 'stock' => 20];

        // No Accept header: API authorization must still return JSON, never a redirect.
        $this->call($method, '/api/products'.($method === 'POST' ? '' : '/'.$product->id), $payload)
            ->assertStatus($status)->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['message']);
        $this->assertSame($before, Product::all()->toArray());
    }

    public function test_product_reads_remain_public(): void
    {
        $product = $this->product();
        $this->getJson('/api/products')->assertOk()->assertJsonFragment(['slug' => $product->slug]);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('id', $product->id);
    }

    public static function permittedActors(): array
    {
        return [['active'], ['both-active']];
    }

    #[DataProvider('permittedActors')]
    public function test_active_admin_can_create_update_and_delete(string $actor): void
    {
        $existing = $this->product();
        $this->authenticateAs($actor);
        $payload = ['category_id' => $existing->category_id, 'name' => 'Created', 'slug' => 'created-'.$actor, 'price' => 250, 'stock' => 20];
        $id = $this->postJson('/api/products', $payload)->assertCreated()->json('id');
        $this->assertDatabaseHas('products', ['id' => $id, 'slug' => $payload['slug']]);
        $this->putJson('/api/products/'.$id, ['name' => 'Updated', 'price' => 300])->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id, 'name' => 'Updated', 'price' => 300]);
        $this->deleteJson('/api/products/'.$id)->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $id]);
        $this->assertDatabaseHas('products', ['id' => $existing->id, 'name' => $existing->name]);
    }

    public static function staleAdminMutations(): array
    {
        return [['POST'], ['PUT'], ['DELETE']];
    }

    #[DataProvider('staleAdminMutations')]
    public function test_password_rotation_rejects_old_admin_session_before_api_product_mutation(string $method): void
    {
        $product = $this->product();
        $admin = $this->admin(true);
        $oldPassword = 'Old-Api-Admin-42!';
        $newPassword = 'New-Api-Admin-84!';
        $admin->update(['password' => $oldPassword]);

        $this->post('/admin/login', ['admin_id' => $admin->admin_id, 'password' => $oldPassword])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->session_version, session(AdminSessionVersion::SESSION_KEY));

        $this->rotatePassword($admin, $newPassword);
        Auth::forgetGuards();

        $before = Product::all()->toArray();
        $payload = ['category_id' => $product->category_id, 'name' => 'Stale mutation', 'slug' => 'stale-mutation', 'price' => 250, 'stock' => 20];
        $url = '/api/products'.($method === 'POST' ? '' : '/'.$product->id);

        $this->app['env'] = 'session-revocation-verification';
        try {
            $this->withSession(['_token' => 'valid-revocation-test-token']);
            $this->json($method, $url, $payload, ['X-CSRF-TOKEN' => 'valid-revocation-test-token'])
                ->assertUnauthorized()
                ->assertJson(['message' => 'Your administrator session has expired. Sign in again.']);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame($before, Product::all()->toArray());
    }

    public function test_fresh_login_after_rotation_restores_api_product_access(): void
    {
        $product = $this->product();
        $admin = $this->admin(true);
        $oldPassword = 'Old-Api-Admin-42!';
        $newPassword = 'New-Api-Admin-84!';
        $admin->update(['password' => $oldPassword]);

        $this->post('/admin/login', ['admin_id' => $admin->admin_id, 'password' => $oldPassword])
            ->assertRedirect(route('admin.dashboard'));
        $this->rotatePassword($admin, $newPassword);
        Auth::forgetGuards();

        $this->app['env'] = 'session-revocation-verification';
        try {
            $this->withSession(['_token' => 'valid-revocation-test-token']);
            $this->deleteJson('/api/products/'.$product->id, [], ['X-CSRF-TOKEN' => 'valid-revocation-test-token'])
                ->assertUnauthorized();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->post('/admin/login', ['admin_id' => $admin->admin_id, 'password' => $newPassword])
            ->assertRedirect(route('admin.dashboard'));

        $payload = ['category_id' => $product->category_id, 'name' => 'Fresh mutation', 'slug' => 'fresh-mutation', 'price' => 250, 'stock' => 20];
        $this->postJson('/api/products', $payload)->assertCreated();
        $this->assertDatabaseHas('products', ['slug' => 'fresh-mutation']);
    }

    public function test_pre_rotation_remembered_admin_cookie_cannot_access_product_api(): void
    {
        $product = $this->product();
        $admin = $this->admin(true);
        $guard = Auth::guard('admin');
        $guard->login($admin, true);
        $recallerName = $guard->getRecallerName();
        $recallerCookie = app('cookie')->queued($recallerName);
        $this->assertNotNull($recallerCookie);

        $this->rotatePassword($admin, 'New-Api-Admin-84!');
        $this->flushSession();
        Auth::forgetGuards();

        $this->app['env'] = 'session-revocation-verification';
        try {
            $this->withSession(['_token' => 'valid-revocation-test-token']);
            $this->withCookie($recallerName, $recallerCookie->getValue())
                ->deleteJson('/api/products/'.$product->id, [], ['X-CSRF-TOKEN' => 'valid-revocation-test-token'])
                ->assertUnauthorized();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_two_factor_completion_establishes_current_session_version_for_api_access(): void
    {
        $product = $this->product();
        $admin = $this->admin(true);
        $admin->forceFill([
            'two_factor_enabled' => true,
            'two_factor_code' => Hash::make('123456'),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->withSession(['pending_admin_id' => $admin->id])
            ->post('/admin/two-factor', ['code' => '123456'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->session_version, session(AdminSessionVersion::SESSION_KEY));

        $payload = ['category_id' => $product->category_id, 'name' => 'Two-factor mutation', 'slug' => 'two-factor-mutation', 'price' => 250, 'stock' => 20];
        $this->postJson('/api/products', $payload)->assertCreated();
        $this->assertDatabaseHas('products', ['slug' => 'two-factor-mutation']);
    }

    private function item(User $user, Product $product): CartItem
    {
        $cart = Cart::create(['user_id' => $user->id]);

        return $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_guest_cannot_delete_cart_item(): void
    {
        $item = $this->item(User::factory()->create(), $this->product());
        $before = CartItem::all()->toArray();
        $this->deleteJson('/api/cart/'.$item->id)->assertUnauthorized();
        $this->assertSame($before, CartItem::all()->toArray());
    }

    public function test_customer_cannot_delete_another_customers_item(): void
    {
        $customer = User::factory()->create();
        $product = $this->product();
        $this->item($customer, $product);
        $other = $this->item(User::factory()->create(), $product);
        $before = CartItem::all()->toArray();
        $this->actingAs($customer, 'web')->deleteJson('/api/cart/'.$other->id)->assertNotFound();
        $this->assertSame($before, CartItem::all()->toArray());
    }

    public static function customerSessions(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('customerSessions')]
    public function test_customer_deletes_only_their_selected_item(bool $withAdminSession): void
    {
        $customer = User::factory()->create();
        $product = $this->product();
        $own = $this->item($customer, $product);
        $this->item($customer, $this->product('second'));
        $this->item(User::factory()->create(), $product);
        $remaining = CartItem::where('id', '!=', $own->id)->get()->toArray();
        $this->actingAs($customer, 'web');
        if ($withAdminSession) {
            $this->actingAs($this->admin(true), 'admin');
        }
        $this->deleteJson('/api/cart/'.$own->id)->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $own->id]);
        $this->assertSame($remaining, CartItem::all()->toArray());
    }

    public function test_missing_cart_item_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->deleteJson('/api/cart/999999')->assertNotFound();
    }

    public static function browserActors(): array
    {
        return [['guest'], ['customer'], ['inactive']];
    }

    #[DataProvider('browserActors')]
    public function test_browser_admin_redirects_are_preserved(string $actor): void
    {
        $this->authenticateAs($actor);
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public static function csrfMutations(): array
    {
        return [['POST'], ['PUT'], ['DELETE'], ['CART_DELETE']];
    }

    #[DataProvider('csrfMutations')]
    public function test_real_csrf_middleware_rejects_missing_and_invalid_tokens_and_accepts_valid_token(string $operation): void
    {
        $product = $this->product();
        $customer = User::factory()->create();
        $item = $this->item($customer, $product);
        $admin = $this->admin(true);
        $this->actingAs($customer, 'web')
            ->actingAs($admin, 'admin')
            ->withSession([AdminSessionVersion::SESSION_KEY => $admin->session_version]);
        $payload = ['category_id' => $product->category_id, 'name' => 'CSRF product', 'slug' => 'csrf-created', 'price' => 250, 'stock' => 20];
        $method = $operation === 'CART_DELETE' ? 'DELETE' : $operation;
        $url = $operation === 'CART_DELETE' ? '/api/cart/'.$item->id : '/api/products'.($method === 'POST' ? '' : '/'.$product->id);
        $productsBefore = Product::all()->toArray();
        $itemsBefore = CartItem::all()->toArray();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        // Disable only Laravel's testing-environment shortcut, not middleware.
        // Already-booted database/session/mail configuration remains isolated.
        $this->app['env'] = 'csrf-verification';
        try {
            $this->assertFalse($this->app->runningUnitTests());
            $this->withSession(['_token' => 'known-test-token']);
            $this->json($method, $url, $payload)->assertStatus(419);
            $this->json($method, $url, $payload, ['X-CSRF-TOKEN' => 'wrong-token'])->assertStatus(419);
            $this->assertSame($productsBefore, Product::all()->toArray());
            $this->assertSame($itemsBefore, CartItem::all()->toArray());
            $this->json($method, $url, $payload, ['X-CSRF-TOKEN' => 'known-test-token'])
                ->assertStatus($method === 'POST' ? 201 : 200);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    private function rotatePassword(Admin $admin, string $password): void
    {
        $command = $this->app->make(RotateAdministratorPassword::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->setInputs([$password, $password]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['admin_id' => $admin->admin_id]));
    }
}
