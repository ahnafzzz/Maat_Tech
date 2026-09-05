<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'lighting'], ['name' => 'Lighting']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Desk Lamp',
            'slug' => 'desk-lamp-'.fake()->unique()->numerify('####'),
            'price' => '100.00',
            'discount_amount' => '10.00',
            'stock' => 10,
            'status' => 'active',
        ], $attributes));
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['phone' => '01700000000'], $attributes));
    }

    private function cartItem(User $user, Product $product, int $quantity = 2): Cart
    {
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $cart;
    }

    private function webPayload(array $overrides = []): array
    {
        return array_merge(['name' => 'Buyer', 'phone' => '01700000000', 'district' => ' DhAkA ',
            'address' => '123 Test Road', 'customer_note' => 'Careful',
            'checkout_attempt_key' => 'web-checkout-key-0001'], $overrides);
    }

    private function apiPayload(array $overrides = []): array
    {
        return array_replace_recursive(['payment_method' => 'cod', 'shipping_method' => 'pathao',
            'shipping_address' => ['name' => 'Buyer', 'phone' => '01700000000', 'city' => ' dhaka ',
                'address' => '123 Test Road']], $overrides);
    }

    public function test_guest_web_checkout_creates_complete_discounted_order_and_preserves_history(): void
    {
        $product = $this->product(['price' => '100.55', 'discount_amount' => '10.20']);
        $response = $this->withSession(['cart' => [$product->id => 2]])->post('/checkout', $this->webPayload());

        $response->assertRedirect(route('orders.index'))->assertSessionHas('order_ids');
        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame('180.70', $order->subtotal);
        $this->assertSame('80.00', $order->shipping_fee);
        $this->assertSame('260.70', $order->total);
        $this->assertSame('Dhaka', $order->district);
        $this->assertSame('Dhaka', $order->shipping_address['city']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price' => 90.35]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 8]);
        $response->assertSessionMissing('cart');
    }

    public function test_authenticated_web_checkout_owns_order_clears_all_own_cart_rows_and_leaves_another_customer_cart(): void
    {
        $customer = $this->user();
        $other = $this->user(['email' => 'other@example.test', 'phone' => '01800000000']);
        $product = $this->product();
        $ownCart = $this->cartItem($customer, $product, 1);
        $secondOwnCart = $this->cartItem($customer, $product, 2);
        $otherCart = $this->cartItem($other, $product, 4);

        $this->actingAs($customer, 'web')->get('/checkout')->assertOk()->assertViewHas('subtotal', '270.00');
        $this->actingAs($customer, 'web')->post('/checkout', $this->webPayload(['district' => 'Sylhet']))
            ->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('270.00', $order->subtotal);
        $this->assertSame('140.00', $order->shipping_fee);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $ownCart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $secondOwnCart->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $otherCart->id, 'quantity' => 4]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);
    }

    public function test_api_checkout_uses_customer_guard_even_with_admin_session_and_ignores_client_totals(): void
    {
        $customer = $this->user();
        $product = $this->product(['price' => '50.25', 'discount_amount' => '0.05']);
        $this->cartItem($customer, $product, 2);
        $admin = Admin::create(['admin_id' => 'ADM-2222-A', 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'status' => 'active']);

        $response = $this->actingAs($customer, 'web')->actingAs($admin, 'admin')->postJson('/api/orders',
            $this->apiPayload(['subtotal' => 1, 'total' => 1, 'user_id' => 999, 'shipping_address' => ['city' => 'Sylhet']]),
            ['Idempotency-Key' => 'api-checkout-key-0001']);

        $response->assertCreated()->assertJsonPath('user_id', $customer->id);
        $order = Order::firstOrFail();
        $this->assertSame('100.40', $order->subtotal);
        $this->assertSame('140.00', $order->shipping_fee);
        $this->assertSame('240.40', $order->total);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('pathao', $order->shipping_method);
        $this->assertSame('50.20', $order->items->first()->unit_price);
    }

    public function test_checkout_preview_uses_shared_discount_and_shipping_policy(): void
    {
        $product = $this->product(['price' => '100.55', 'discount_amount' => '10.20']);
        $this->withSession(['cart' => [$product->id => 2]])->get('/checkout')
            ->assertOk()->assertViewHas('subtotal', '180.70')->assertViewHas('shippingFee', null)
            ->assertSee('Select district');
        $customer = $this->user(['district' => ' dhaka ']);
        $this->cartItem($customer, $product, 2);
        $this->actingAs($customer)->get('/checkout')->assertOk()
            ->assertViewHas('subtotal', '180.70')->assertViewHas('shippingFee', '80.00')->assertViewHas('total', '260.70');
    }

    public function test_empty_and_invalid_guest_carts_are_rejected_and_preserved(): void
    {
        $this->post('/checkout', $this->webPayload())->assertSessionHasErrors('cart');
        $product = $this->product();
        foreach ([[0], [-1], ['2']] as $invalid) {
            $cart = [$product->id => $invalid[0]];
            $this->withSession(['cart' => $cart])->post('/checkout', $this->webPayload())
                ->assertSessionHasErrors('cart')->assertSessionHas('cart', $cart);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_missing_inactive_and_insufficient_guest_products_fail_without_clearing_cart(): void
    {
        $inactive = $this->product(['status' => 'draft']);
        $low = $this->product(['name' => 'Low Stock', 'stock' => 1]);
        foreach ([[$inactive->id => 1], [$low->id => 2], [999999 => 1]] as $cart) {
            $this->withSession(['cart' => $cart])->post('/checkout', $this->webPayload())
                ->assertSessionHasErrors('cart')->assertSessionHas('cart', $cart);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $low->fresh()->stock);
    }

    public function test_invalid_database_quantity_fails_and_preserves_cart(): void
    {
        $customer = $this->user();
        $cart = $this->cartItem($customer, $this->product(), 0);
        $this->actingAs($customer)->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => 'api-checkout-key-0002'])
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 0]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unsupported_methods_and_invalid_or_conflicting_districts_return_json_validation_errors(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $cases = [
            [$this->apiPayload(['payment_method' => 'card']), 'payment_method'],
            [$this->apiPayload(['shipping_method' => 'pickup']), 'shipping_method'],
            [$this->apiPayload(['shipping_address' => ['city' => 'Atlantis']]), 'shipping_address.city'],
            [$this->apiPayload(['shipping_address' => ['city' => null, 'district' => null]]), 'shipping_address.district'],
            [$this->apiPayload(['shipping_address' => ['city' => 'Dhaka', 'district' => 'Sylhet']]), 'shipping_address.city'],
        ];
        foreach ($cases as [$payload, $field]) {
            if (! Cart::where('user_id', $customer->id)->exists()) {
                $this->cartItem($customer, $product, 1);
            }
            $this->actingAs($customer)->postJson('/api/orders', $payload, ['Idempotency-Key' => 'api-checkout-key-0003'])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failure_during_item_creation_rolls_back_order_stock_and_cart(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $cart = $this->cartItem($customer, $product, 2);
        $secondProduct = $this->product(['name' => 'Second Lamp']);
        $cart->items()->create(['product_id' => $secondProduct->id, 'quantity' => 1]);
        $createdItems = 0;
        OrderItem::creating(function () use (&$createdItems): void {
            if (++$createdItems % 2 === 0) {
                throw new RuntimeException('Injected persistence failure');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($customer)->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => 'api-checkout-key-0004']);
            $this->fail('Expected injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected persistence failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 10]);
        $this->assertDatabaseHas('products', ['id' => $secondProduct->id, 'stock' => 10]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 2]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $secondProduct->id, 'quantity' => 1]);
    }

    public function test_orders_in_same_second_have_distinct_numbers(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');
        foreach ([1, 2] as $number) {
            $customer = $this->user(['email' => "buyer$number@example.test", 'phone' => "0170000000$number"]);
            $this->cartItem($customer, $this->product(['name' => "Lamp $number"]), 1);
            $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(),
                ['Idempotency-Key' => 'api-checkout-key-'.$number])->assertCreated();
            $this->app['auth']->forgetGuards();
        }
        $this->assertCount(2, Order::pluck('order_number')->unique());
    }

    public function test_sequential_attempt_after_stock_exhaustion_cannot_oversell_and_keeps_failed_cart(): void
    {
        $product = $this->product(['stock' => 1]);
        $first = $this->user(['email' => 'first@example.test']);
        $second = $this->user(['email' => 'second@example.test', 'phone' => '01800000000']);
        $this->cartItem($first, $product, 1);
        $secondCart = $this->cartItem($second, $product, 1);

        $this->actingAs($first)->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => 'api-checkout-key-first'])->assertCreated();
        $this->actingAs($second)->postJson('/api/orders', $this->apiPayload(), ['Idempotency-Key' => 'api-checkout-key-second'])
            ->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $secondCart->id, 'quantity' => 1]);
    }
}
