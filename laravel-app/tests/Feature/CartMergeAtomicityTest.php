<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\CartMergeService;
use App\Services\GuestMergeIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CartMergeAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'merge'], ['name' => 'Merge']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Merge Lamp',
            'slug' => 'merge-lamp-'.fake()->unique()->numerify('####'),
            'price' => '100.00',
            'stock' => 20,
            'status' => 'active',
        ], $attributes));
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'customer-password',
        ], $attributes));
    }

    private function requestWith(array $sessionData): Request
    {
        $session = $this->app['session']->driver();
        $session->flush();
        foreach ($sessionData as $key => $value) {
            $session->put($key, $value);
        }
        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }

    public function test_later_wishlist_failure_rolls_back_the_whole_cart_and_wishlist_merge(): void
    {
        $customer = $this->user();
        $cartProduct = $this->product();
        $firstWishlistProduct = $this->product();
        $failingWishlistProduct = $this->product();
        $request = $this->requestWith([
            'cart' => [$cartProduct->id => 2],
            'wishlist' => [$firstWishlistProduct->id, $failingWishlistProduct->id],
        ]);
        $createdWishlistItems = 0;
        WishlistItem::creating(function () use (&$createdWishlistItems): void {
            if (++$createdWishlistItems === 2) {
                throw new RuntimeException('Injected later merge failure');
            }
        });

        try {
            app(CartMergeService::class)->merge($request, $customer);
            $this->fail('Expected the injected later merge failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected later merge failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('carts', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('wishlists', ['user_id' => $customer->id]);
        $this->assertDatabaseCount('cart_merge_attempts', 0);
        $this->assertSame([$cartProduct->id => 2], $request->session()->get('cart'));
        $this->assertSame([$firstWishlistProduct->id, $failingWishlistProduct->id], $request->session()->get('wishlist'));
    }

    public function test_retry_after_database_commit_before_session_cleanup_does_not_add_twice(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $request = $this->requestWith(['cart' => [$product->id => 2]]);
        $service = app(CartMergeService::class);
        $snapshot = $service->capture($request);

        $this->assertFalse($service->applySnapshot($customer, $snapshot));
        $this->assertSame(3, $cart->items()->sole()->quantity);
        $this->assertTrue($request->session()->has('cart'));

        $this->assertTrue($service->applySnapshot($customer, $snapshot));
        $this->assertSame(3, $cart->items()->sole()->quantity);
        $service->cleanupSnapshot($request, $snapshot);

        $this->assertFalse($request->session()->has('cart'));
        $this->assertDatabaseCount('cart_merge_attempts', 1);
    }

    public function test_a_completed_guest_snapshot_cannot_be_merged_into_a_second_customer(): void
    {
        $firstCustomer = $this->user();
        $secondCustomer = $this->user();
        $product = $this->product();
        $request = $this->requestWith(['cart' => [$product->id => 2]]);
        $service = app(CartMergeService::class);
        $snapshot = $service->capture($request);

        $service->applySnapshot($firstCustomer, $snapshot);

        try {
            $service->applySnapshot($secondCustomer, $snapshot);
            $this->fail('Expected the consumed guest snapshot to remain bound to the first customer.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('different customer account', $exception->errors()['cart'][0]);
        }

        $firstCart = Cart::where('user_id', $firstCustomer->id)->sole();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $firstCart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('carts', ['user_id' => $secondCustomer->id]);
        $this->assertDatabaseCount('cart_merge_attempts', 1);
    }

    public function test_items_added_after_capture_survive_snapshot_cleanup(): void
    {
        $customer = $this->user();
        $capturedProduct = $this->product();
        $newProduct = $this->product();
        $request = $this->requestWith([
            'cart' => [$capturedProduct->id => 2],
            'wishlist' => [$capturedProduct->id],
        ]);
        $service = app(CartMergeService::class);
        $snapshot = $service->capture($request);
        $request->session()->put('cart', [$capturedProduct->id => 3, $newProduct->id => 1]);
        $request->session()->put('wishlist', [$capturedProduct->id, $newProduct->id]);
        app(GuestMergeIdentity::class)->markChanged($request);
        $newMergeKey = $request->session()->get(GuestMergeIdentity::SESSION_KEY);

        $service->applySnapshot($customer, $snapshot);
        $service->cleanupSnapshot($request, $snapshot);

        $this->assertDatabaseHas('cart_items', ['product_id' => $capturedProduct->id, 'quantity' => 2]);
        $this->assertSame([$capturedProduct->id => 1, $newProduct->id => 1], $request->session()->get('cart'));
        $this->assertSame([$newProduct->id], $request->session()->get('wishlist'));
        $this->assertSame($newMergeKey, $request->session()->get(GuestMergeIdentity::SESSION_KEY));
    }

    public function test_deleted_and_unpublished_snapshot_products_are_skipped_and_cleaned_after_success(): void
    {
        $customer = $this->user();
        $active = $this->product();
        $draft = $this->product(['status' => 'draft']);
        $deleted = $this->product();
        $request = $this->requestWith([
            'cart' => [$active->id => 2, $draft->id => 3, $deleted->id => 4],
            'wishlist' => [$active->id, $draft->id, $deleted->id],
        ]);
        $snapshot = app(CartMergeService::class)->capture($request);
        $deleted->delete();

        app(CartMergeService::class)->merge($request, $customer, $snapshot);

        $this->assertDatabaseHas('cart_items', ['product_id' => $active->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $draft->id]);
        $this->assertDatabaseHas('wishlist_items', ['product_id' => $active->id]);
        $this->assertDatabaseMissing('wishlist_items', ['product_id' => $draft->id]);
        $this->assertFalse($request->session()->has('cart'));
        $this->assertFalse($request->session()->has('wishlist'));
    }

    public function test_actual_login_merges_once_and_clears_only_the_captured_snapshot(): void
    {
        $customer = $this->user(['email' => 'login-merge@example.test']);
        $product = $this->product();
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->withSession(['cart' => [$product->id => 2], 'wishlist' => [$product->id]])
            ->post(route('login.store'), ['email' => $customer->email, 'password' => 'customer-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3]);
        $this->assertDatabaseHas('wishlist_items', ['product_id' => $product->id]);
        $this->assertDatabaseCount('cart_merge_attempts', 1);
        $this->assertFalse(session()->has('cart'));
        $this->assertFalse(session()->has('wishlist'));
    }

    public function test_actual_registration_merges_into_the_new_customer(): void
    {
        $product = $this->product();

        $this->withSession(['cart' => [$product->id => 2]])->post(route('register.store'), [
            'name' => 'New Customer',
            'email' => 'registered-merge@example.test',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
        ])->assertRedirect(route('dashboard'));

        $customer = User::where('email', 'registered-merge@example.test')->sole();
        $this->assertAuthenticatedAs($customer, 'web');
        $cart = Cart::where('user_id', $customer->id)->sole();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseCount('cart_merge_attempts', 1);
        $this->assertFalse(session()->has('cart'));
    }

    public function test_registration_and_merge_roll_back_together_after_a_later_failure(): void
    {
        $cartProduct = $this->product();
        $wishlistProducts = [$this->product(), $this->product()];
        $createdWishlistItems = 0;
        WishlistItem::creating(function () use (&$createdWishlistItems): void {
            if (++$createdWishlistItems === 2) {
                throw new RuntimeException('Injected registration merge failure');
            }
        });

        $this->withSession([
            'cart' => [$cartProduct->id => 2],
            'wishlist' => collect($wishlistProducts)->pluck('id')->all(),
        ])->withoutExceptionHandling();

        try {
            $this->post(route('register.store'), [
                'name' => 'Rollback Customer',
                'email' => 'rollback-registration@example.test',
                'password' => 'customer-password',
                'password_confirmation' => 'customer-password',
            ]);
            $this->fail('Expected the injected registration merge failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected registration merge failure', $exception->getMessage());
        }

        $this->assertGuest('web');
        $this->assertDatabaseMissing('users', ['email' => 'rollback-registration@example.test']);
        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('wishlists', 0);
        $this->assertDatabaseCount('cart_merge_attempts', 0);
        $this->assertSame([$cartProduct->id => 2], session('cart'));
        $this->assertSame(collect($wishlistProducts)->pluck('id')->all(), session('wishlist'));
    }

    public function test_failed_login_merge_logs_the_customer_back_out_and_preserves_the_snapshot(): void
    {
        $customer = $this->user(['email' => 'rollback-login@example.test']);
        $cartProduct = $this->product();
        $wishlistProducts = [$this->product(), $this->product()];
        $createdWishlistItems = 0;
        WishlistItem::creating(function () use (&$createdWishlistItems): void {
            if (++$createdWishlistItems === 2) {
                throw new RuntimeException('Injected login merge failure');
            }
        });

        $this->withSession([
            'cart' => [$cartProduct->id => 2],
            'wishlist' => collect($wishlistProducts)->pluck('id')->all(),
        ])->withoutExceptionHandling();

        try {
            $this->post(route('login.store'), [
                'email' => $customer->email,
                'password' => 'customer-password',
            ]);
            $this->fail('Expected the injected login merge failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected login merge failure', $exception->getMessage());
        }

        $this->assertGuest('web');
        $this->assertDatabaseMissing('carts', ['user_id' => $customer->id]);
        $this->assertDatabaseMissing('wishlists', ['user_id' => $customer->id]);
        $this->assertDatabaseCount('cart_merge_attempts', 0);
        $this->assertSame([$cartProduct->id => 2], session('cart'));
        $this->assertSame(collect($wishlistProducts)->pluck('id')->all(), session('wishlist'));
    }

    public function test_api_cart_read_does_not_create_persistent_state(): void
    {
        $customer = $this->user();

        $this->actingAs($customer, 'web')->getJson('/api/cart')
            ->assertOk()->assertJsonPath('id', null)->assertJsonPath('items', []);

        $this->assertDatabaseMissing('carts', ['user_id' => $customer->id]);
    }
}
