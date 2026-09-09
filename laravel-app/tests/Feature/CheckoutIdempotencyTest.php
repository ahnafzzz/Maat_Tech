<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'checkout'], ['name' => 'Checkout']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Idempotent Lamp',
            'slug' => 'idempotent-lamp-'.fake()->unique()->numerify('####'),
            'price' => '100.00',
            'discount_amount' => '10.00',
            'stock' => 10,
            'status' => 'active',
        ], $attributes));
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['phone' => '01700000000'], $attributes));
    }

    private function cart(User $customer, Product $product, int $quantity = 1): Cart
    {
        $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $cart;
    }

    private function webPayload(string $key, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Buyer',
            'phone' => '01700000000',
            'district' => 'Dhaka',
            'address' => '123 Test Road',
            'customer_note' => 'Careful',
            'checkout_attempt_key' => $key,
        ], $overrides);
    }

    private function apiPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
            'shipping_address' => [
                'name' => 'Buyer',
                'phone' => '01700000000',
                'city' => 'Dhaka',
                'address' => '123 Test Road',
            ],
            'customer_note' => 'Careful',
        ], $overrides);
    }

    public function test_guest_same_key_replay_returns_one_order_and_restores_history_without_touching_new_cart(): void
    {
        $product = $this->product();
        $newProduct = $this->product(['name' => 'New Lamp']);
        $checkout = $this->withSession(['cart' => [$product->id => 2]])->get('/checkout')->assertOk();
        preg_match('/name="checkout_attempt_key" value="([^"]+)"/', $checkout->getContent(), $matches);
        $key = $matches[1];
        $identity = session('checkout_guest_identity');
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $identity);

        $first = $this->post('/checkout', $this->webPayload($key));
        $first->assertRedirect(route('orders.index'));
        $order = Order::firstOrFail();

        $retry = $this->withSession([
            'cart' => [$newProduct->id => 1],
            'order_ids' => [],
            'checkout_guest_identity' => $identity,
        ])->post('/checkout', $this->webPayload($key));

        $retry->assertRedirect(route('orders.index'))->assertSessionHas('order_ids', [$order->id]);
        $retry->assertSessionHas('cart', [$newProduct->id => 1]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(10, $newProduct->fresh()->stock);
    }

    public function test_customer_web_retry_replays_empty_cart_and_preserves_new_items(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $newProduct = $this->product(['name' => 'Later Lamp']);
        $this->cart($customer, $product, 2);
        $key = 'customer-web-key-0001';

        $this->actingAs($customer, 'web')->post('/checkout', $this->webPayload($key))->assertRedirect(route('orders.index'));
        $order = Order::firstOrFail();
        $newCart = $this->cart($customer, $newProduct, 1);

        $this->post('/checkout', $this->webPayload($key))->assertRedirect(route('orders.index'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $newCart->id, 'product_id' => $newProduct->id, 'quantity' => 1]);
    }

    public function test_api_retry_returns_original_order_without_current_cart_or_product_revalidation(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $this->cart($customer, $product, 2);
        $headers = ['Idempotency-Key' => 'api-replay-key-0001'];

        $first = $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'false');
        $orderId = $first->json('id');
        $product->update(['status' => 'draft', 'price' => '999.00']);

        $retry = $this->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $retry->assertJsonPath('id', $orderId);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_different_key_allows_an_intentional_identical_purchase(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $this->cart($customer, $product);
        $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(),
            ['Idempotency-Key' => 'intentional-key-0001'])->assertCreated();
        $this->cart($customer, $product);
        $this->postJson('/api/orders', $this->apiPayload(),
            ['Idempotency-Key' => 'intentional-key-0002'])->assertCreated();

        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_conflicting_details_with_same_key_return_conflict_without_mutation(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $this->cart($customer, $product);
        $headers = ['Idempotency-Key' => 'conflicting-key-0001'];
        $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)->assertCreated();
        $newCart = $this->cart($customer, $product);

        $this->postJson('/api/orders', $this->apiPayload(['shipping_address' => ['address' => 'Different Road']]), $headers)
            ->assertStatus(409)->assertJsonValidationErrors('checkout_attempt_key');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $newCart->id, 'quantity' => 1]);
    }

    public function test_attempt_keys_are_scoped_to_each_customer_and_guest_identity(): void
    {
        $product = $this->product();
        $key = 'shared-scope-key-0001';
        $first = $this->customer(['email' => 'first@example.test']);
        $second = $this->customer(['email' => 'second@example.test', 'phone' => '01800000000']);
        $this->cart($first, $product);
        $this->cart($second, $product);

        $firstOrder = $this->actingAs($first, 'web')->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => $key])
            ->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();
        $secondOrder = $this->actingAs($second, 'web')->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => $key])
            ->assertCreated()->json('id');
        $this->assertNotSame($firstOrder, $secondOrder);

        $this->app['auth']->forgetGuards();
        $firstGuest = $this->withSession(['cart' => [$product->id => 1], 'checkout_guest_identity' => str_repeat('b', 64)])
            ->post('/checkout', $this->webPayload($key));
        $firstGuest->assertRedirect(route('orders.index'));
        $firstGuestOrder = Order::latest('id')->value('id');
        $secondGuest = $this->withSession(['cart' => [$product->id => 1], 'checkout_guest_identity' => str_repeat('c', 64)])
            ->post('/checkout', $this->webPayload($key));
        $secondGuest->assertRedirect(route('orders.index'));

        $this->assertNotSame($firstGuestOrder, Order::latest('id')->value('id'));
        $this->assertDatabaseCount('orders', 4);
    }

    public function test_api_requires_a_valid_idempotency_key_without_mutation(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $cart = $this->cart($customer, $product);

        foreach ([null, 'short', 'invalid key with spaces'] as $key) {
            $headers = $key === null ? [] : ['Idempotency-Key' => $key];
            $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)
                ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('checkout_attempts', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 1]);
    }

    public function test_validation_failure_leaves_no_attempt_and_same_key_can_succeed_later(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $key = 'validation-retry-key-0001';
        $headers = ['Idempotency-Key' => $key];

        $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('checkout_attempts', 0);

        $this->cart($customer, $product);
        $this->postJson('/api/orders', $this->apiPayload(), $headers)->assertCreated();
        $this->assertDatabaseCount('checkout_attempts', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_mid_transaction_failure_rolls_back_attempt_and_retry_succeeds(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $cart = $this->cart($customer, $product, 2);
        $failOnce = true;
        OrderItem::creating(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;
                throw new RuntimeException('Injected idempotency failure');
            }
        });
        $headers = ['Idempotency-Key' => 'rollback-retry-key-0001'];

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers);
            $this->fail('Expected injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected idempotency failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('checkout_attempts', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 2]);

        $this->withExceptionHandling()->postJson('/api/orders', $this->apiPayload(), $headers)->assertCreated();
        $this->assertDatabaseCount('checkout_attempts', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_database_unique_constraint_rejects_duplicate_owner_key_without_application_precheck(): void
    {
        $attributes = [
            'owner_type' => 'customer',
            'owner_identifier' => '123',
            'attempt_key' => 'database-unique-key-0001',
            'fingerprint' => str_repeat('d', 64),
        ];
        CheckoutAttempt::create($attributes);

        try {
            CheckoutAttempt::create($attributes);
            $this->fail('Expected the database unique constraint to reject the duplicate.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('checkout_attempt', strtolower($exception->getMessage()));
        }

        $this->assertDatabaseCount('checkout_attempts', 1);
    }

    public function test_web_form_generates_and_preserves_attempt_key_after_validation_error(): void
    {
        $product = $this->product();
        $checkout = $this->withSession(['cart' => [$product->id => 1]])->get('/checkout')->assertOk();
        preg_match('/name="checkout_attempt_key" value="([^"]+)"/', $checkout->getContent(), $matches);
        $key = $matches[1] ?? '';
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9][A-Za-z0-9._:-]{15,127}\z/', $key);

        $response = $this->from('/checkout')->post('/checkout', $this->webPayload($key, ['phone' => '']));
        $response->assertRedirect('/checkout')->assertSessionHasErrors('phone')->assertSessionHasInput('checkout_attempt_key', $key);
        $this->get('/checkout')->assertOk()->assertSee('value="'.$key.'"', false);
        $this->assertDatabaseCount('checkout_attempts', 0);
    }
}
