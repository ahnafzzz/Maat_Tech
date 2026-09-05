<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'history'], ['name' => 'History']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Historical Lamp',
            'slug' => 'historical-lamp-'.fake()->unique()->numerify('####'),
            'sku' => 'HIST-001',
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

    private function order(?User $customer, Product $product, array $item = []): Order
    {
        $order = Order::create([
            'user_id' => $customer?->id,
            'order_number' => 'HISTORY-'.fake()->unique()->numerify('######'),
            'subtotal' => '180.00',
            'shipping_fee' => '80.00',
            'total' => '260.00',
            'customer_name' => 'Historical Buyer',
            'customer_phone' => '01700000000',
            'district' => 'Dhaka',
            'address' => 'Archive Road',
            'placed_at' => now(),
        ]);
        $order->items()->create(array_merge([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => '90.00',
        ], $item));

        return $order;
    }

    private function apiPayload(): array
    {
        return [
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
            'shipping_address' => [
                'name' => 'Buyer',
                'phone' => '01700000000',
                'city' => 'Dhaka',
                'address' => '123 Test Road',
            ],
        ];
    }

    public function test_checkout_snapshots_identity_and_catalog_changes_do_not_rewrite_history(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $created = $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(),
            ['Idempotency-Key' => 'history-checkout-key-0001'])->assertCreated();
        $orderId = $created->json('id');
        $created->assertJsonPath('items.0.product_name', 'Historical Lamp')
            ->assertJsonPath('items.0.product_sku', 'HIST-001')
            ->assertJsonMissingPath('items.0.product');

        $product->update(['name' => 'Renamed Lamp', 'sku' => 'NEW-999', 'price' => '999.00']);
        $detail = $this->getJson('/api/orders/'.$orderId)->assertOk();
        $detail->assertJsonPath('items.0.product_name', 'Historical Lamp')
            ->assertJsonPath('items.0.product_sku', 'HIST-001')
            ->assertJsonPath('items.0.unit_price', '90.00');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_name' => 'Historical Lamp',
            'product_sku' => 'HIST-001',
            'quantity' => 2,
            'unit_price' => 90,
        ]);
    }

    public function test_product_and_category_deletion_preserve_orders_items_and_safe_views(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $category = $product->category;
        $order = $this->order($customer, $product);

        $category->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => 260]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => 'Historical Lamp',
            'quantity' => 2,
            'unit_price' => 90,
        ]);
        $this->actingAs($customer, 'web')->get('/orders')->assertOk()
            ->assertSee('Historical Lamp')->assertSee('HIST-001');
    }

    public function test_customer_deletion_preserves_history_without_reassignment(): void
    {
        $customer = $this->customer(['email' => 'historical@example.test']);
        $product = $this->product();
        $order = $this->order($customer, $product);

        $customer->delete();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null, 'total' => 260]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 2]);
        $replacement = $this->customer(['email' => 'historical@example.test', 'phone' => '01800000000']);
        $this->actingAs($replacement, 'web')->getJson('/api/orders/'.$order->id)->assertNotFound();
    }

    public function test_order_detail_is_owner_scoped_and_hides_attempt_metadata(): void
    {
        $owner = $this->customer(['email' => 'owner@example.test']);
        $foreign = $this->customer(['email' => 'foreign@example.test', 'phone' => '01800000000']);
        $product = $this->product();
        $order = $this->order($owner, $product);
        $guestOrder = $this->order(null, $product);

        $response = $this->actingAs($owner, 'web')->getJson('/api/orders/'.$order->id)->assertOk();
        $response->assertJsonPath('id', $order->id)
            ->assertJsonPath('items.0.product_name', 'Historical Lamp')
            ->assertJsonMissingPath('checkout_attempt')
            ->assertJsonMissingPath('attempt_key')
            ->assertJsonMissingPath('owner_identifier');

        $this->app['auth']->forgetGuards();
        $this->actingAs($foreign, 'web')->getJson('/api/orders/'.$order->id)->assertNotFound();
        $this->getJson('/api/orders/'.$guestOrder->id)->assertNotFound();
        $this->getJson('/api/orders/999999')->assertNotFound();
    }

    public function test_guest_admin_and_mixed_sessions_keep_customer_order_authorization(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $order = $this->order($customer, $product);
        $admin = Admin::create([
            'admin_id' => 'ADM-4000-X',
            'name' => 'Admin',
            'email' => 'admin-history@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->getJson('/api/orders/'.$order->id)->assertUnauthorized();
        $this->actingAs($admin, 'admin')->getJson('/api/orders/'.$order->id)->assertUnauthorized();
        $this->actingAs($customer, 'web')->deleteJson('/api/products/'.$product->id)->assertOk();
        $this->getJson('/api/orders/'.$order->id)->assertOk()
            ->assertJsonPath('user_id', $customer->id)
            ->assertJsonPath('items.0.product_name', 'Historical Lamp')
            ->assertJsonPath('items.0.product_id', null);
    }

    public function test_guest_order_history_remains_limited_to_session_order_ids(): void
    {
        $product = $this->product();
        $authorized = $this->order(null, $product);
        $unrelated = $this->order(null, $product);

        $this->withSession(['order_ids' => [$authorized->id]])->get('/orders')->assertOk()
            ->assertSee($authorized->order_number)->assertDontSee($unrelated->order_number);
        $this->withSession(['order_ids' => []])->get('/orders')->assertOk()
            ->assertDontSee($authorized->order_number)->assertDontSee($unrelated->order_number);
    }

    public function test_idempotent_replay_after_product_deletion_returns_original_snapshot(): void
    {
        $customer = $this->customer();
        $product = $this->product(['stock' => 2]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $headers = ['Idempotency-Key' => 'deleted-product-replay-0001'];

        $first = $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated();
        $orderId = $first->json('id');
        $product->delete();

        $retry = $this->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $retry->assertJsonPath('id', $orderId)
            ->assertJsonPath('items.0.product_name', 'Historical Lamp')
            ->assertJsonPath('items.0.product_sku', 'HIST-001')
            ->assertJsonPath('items.0.product_id', null);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }
}
