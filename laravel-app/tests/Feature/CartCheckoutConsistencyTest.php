<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartCheckoutConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);

        return Product::create(array_merge(['category_id' => $category->id, 'name' => 'Lamp', 'slug' => 'lamp',
            'price' => '100.00', 'discount_amount' => '10.00', 'stock' => 20, 'status' => 'active'], $attributes));
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['phone' => '01700000000'], $attributes));
    }

    private function cart(User $user, Product $product, int $quantity = 2): Cart
    {
        $cart = Cart::create(['user_id' => $user->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $cart;
    }

    private function checkoutPayload(): array
    {
        return ['name' => 'Buyer', 'phone' => '01700000000', 'district' => 'Dhaka', 'address' => 'Test Road',
            'checkout_attempt_key' => 'web-checkout-key-0001'];
    }

    private function apiPayload(array $shippingOverrides = []): array
    {
        return ['payment_method' => 'cod', 'shipping_method' => 'pathao', 'shipping_address' => array_merge([
            'name' => 'Buyer', 'phone' => '01700000000', 'city' => 'Dhaka', 'address' => 'Test Road',
        ], $shippingOverrides)];
    }

    public function test_cart_update_matches_preview_and_checkout_and_preserves_other_customer(): void
    {
        $customer = $this->user();
        $other = $this->user(['email' => 'other@example.test', 'phone' => '01800000000']);
        $product = $this->product();
        $this->cart($customer, $product, 5);
        $otherCart = $this->cart($other, $product, 4);

        $this->actingAs($customer, 'web')->get('/cart')->assertOk()->assertSee('value="5"', false);
        $this->get('/checkout')->assertOk()->assertViewHas('subtotal', '450.00');
        $this->post('/cart/update/'.$product->id, ['quantity' => 4])->assertRedirect();
        $this->get('/cart')->assertOk()->assertSee('value="4"', false);
        $this->post('/checkout', $this->checkoutPayload())->assertRedirect(route('orders.index'));

        $this->assertDatabaseHas('order_items', ['order_id' => Order::firstOrFail()->id, 'quantity' => 4]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $otherCart->id, 'quantity' => 4]);
    }

    public function test_cart_remove_deletes_the_owned_product_and_checkout_sees_empty_cart(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $this->cart($customer, $product, 5);

        $this->actingAs($customer, 'web')->post('/cart/remove/'.$product->id)->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
        $this->get('/checkout')->assertRedirect();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_repeated_cart_add_uses_one_item_and_respects_stock_limit(): void
    {
        $customer = $this->user();
        $product = $this->product(['stock' => 6]);
        $this->cart($customer, $product, 5);

        $this->actingAs($customer, 'web')->post('/cart/add/'.$product->id, ['quantity' => 4])->assertRedirect();
        $this->post('/cart/add/'.$product->id, ['quantity' => 1])->assertRedirect();
        $this->assertSame(1, Cart::where('user_id', $customer->id)->count());
        $this->assertSame(1, CartItem::count());
        $this->get('/cart')->assertOk()->assertSee('value="6"', false);
        $this->post('/checkout', $this->checkoutPayload())->assertRedirect(route('orders.index'));
        $this->assertDatabaseHas('order_items', ['order_id' => Order::firstOrFail()->id, 'quantity' => 6]);
    }

    public function test_owned_api_item_deletion_preserves_another_customers_item(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $firstCart = $this->cart($customer, $product, 2);
        $other = $this->user(['email' => 'api-other@example.test', 'phone' => '01800000000']);
        $otherCart = $this->cart($other, $product, 3);
        $firstItem = $firstCart->items()->firstOrFail();

        $this->actingAs($customer, 'web')->deleteJson('/api/cart/'.$firstItem->id)->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $firstItem->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $otherCart->id, 'product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_api_add_and_login_merge_preserve_the_unique_customer_cart_scope(): void
    {
        $customer = $this->user(['email' => 'merge@example.test']);
        $product = $this->product(['stock' => 9]);
        $this->cart($customer, $product, 5);

        $this->actingAs($customer, 'web')->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->assertSame(7, Cart::where('user_id', $customer->id)->firstOrFail()->items()->sum('quantity'));
        $this->assertDatabaseCount('cart_items', 1);

        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('cart', [$product->id => 4]);
        $this->app->make(CartMergeService::class)->merge($request, $customer);
        $this->assertSame(9, Cart::where('user_id', $customer->id)->firstOrFail()->items()->sum('quantity'));
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertFalse($request->session()->has('cart'));
    }

    public static function pageIdentities(): array
    {
        return [['guest'], ['customer'], ['admin'], ['both']];
    }

    #[DataProvider('pageIdentities')]
    public function test_cart_and_checkout_pages_resolve_only_the_web_customer(string $identity): void
    {
        $product = $this->product();
        $customer = $this->user();
        $admin = Admin::create(['admin_id' => 'ADM-1234-X', 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'status' => 'active']);
        $this->cart($customer, $product, 2);
        if (in_array($identity, ['customer', 'both'])) {
            $this->actingAs($customer, 'web');
        }
        if (in_array($identity, ['admin', 'both'])) {
            $this->actingAs($admin, 'admin');
        }

        $expectedQuantity = in_array($identity, ['customer', 'both']) ? '2' : null;
        $cartResponse = $this->get('/cart')->assertOk();
        $checkoutResponse = $this->get('/checkout');
        if ($expectedQuantity) {
            $cartResponse->assertSee('value="'.$expectedQuantity.'"', false);
            $checkoutResponse->assertOk();
        } else {
            $cartResponse->assertSee('Your cart buffer is empty.');
            $checkoutResponse->assertRedirect();
        }
    }

    public function test_customer_and_admin_session_creates_order_for_customer(): void
    {
        $customer = $this->user();
        $product = $this->product();
        $this->cart($customer, $product, 2);
        $admin = Admin::create(['admin_id' => 'ADM-1234-X', 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password', 'status' => 'active']);

        $this->actingAs($customer, 'web')->actingAs($admin, 'admin')->post('/checkout', $this->checkoutPayload())
            ->assertRedirect(route('orders.index'));
        $this->assertSame($customer->id, Order::firstOrFail()->user_id);
    }

    public function test_api_rejects_missing_or_blank_effective_phone_before_mutation(): void
    {
        $missingPhone = $this->apiPayload();
        unset($missingPhone['shipping_address']['phone']);
        $blankPhone = $this->apiPayload(['phone' => '']);
        foreach ([$missingPhone, $blankPhone] as $payload) {
            $customer = $this->user(['phone' => null, 'email' => fake()->unique()->safeEmail()]);
            $product = Product::first() ?? $this->product();
            $cart = Cart::create(['user_id' => $customer->id]);
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
            $this->actingAs($customer, 'web')->postJson('/api/orders', $payload, ['Idempotency-Key' => 'api-contact-key-'.$customer->id])
                ->assertUnprocessable()->assertJsonValidationErrors('shipping_address.phone');
            $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 1]);
            $this->assertSame(20, $product->fresh()->stock);
            $this->app['auth']->forgetGuards();
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_api_accepts_valid_profile_phone_fallback_and_explicit_phone(): void
    {
        foreach ([[[], '01700000001'], [['phone' => '01900000000'], '01700000002']] as [$shipping, $profilePhone]) {
            $customer = $this->user(['email' => fake()->unique()->safeEmail(), 'phone' => $profilePhone]);
            $product = Product::first() ?? $this->product();
            $cart = Cart::create(['user_id' => $customer->id]);
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
            $payload = $this->apiPayload($shipping);
            if ($shipping === []) {
                unset($payload['shipping_address']['phone']);
            }
            $response = $this->actingAs($customer, 'web')->postJson('/api/orders', $payload,
                ['Idempotency-Key' => 'api-contact-key-'.$customer->id])->assertCreated();
            $response->assertJsonPath('customer_phone', $shipping['phone'] ?? $customer->phone);
            $this->app['auth']->forgetGuards();
        }
        $this->assertDatabaseCount('orders', 2);
    }
}
