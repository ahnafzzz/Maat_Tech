<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\AdminSessionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Desk Lamps', 'slug' => 'desk-lamps']);
    }

    public function test_admin_discount_is_derived_in_integer_money_and_drives_home_and_product_prices(): void
    {
        config(['product-showcases.products.admin-offer-lamp' => [
            'model_id' => 'offer-lamp',
            'manifest' => 'assets/models/desk-lamp/desk-lamp.4843375218151318.json',
            'poster' => 'assets/models/desk-lamp/desk-lamp-poster.webp',
        ]]);
        $product = $this->lamp(['slug' => 'admin-offer-lamp']);

        $this->get('/')->assertOk()->assertSee('25% off')->assertDontSee('৳2,499');
        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('৳3,332')->assertSee('৳2,499')->assertSee('You save ৳833');

        $admin = Admin::create([
            'admin_id' => 'ADM-2500-A',
            'name' => 'Catalog Admin',
            'email' => 'offer-admin@example.test',
            'password' => 'password',
            'status' => 'active',
            'session_version' => Str::random(64),
            'two_factor_enabled' => true,
        ]);
        $this->actingAs($admin, 'admin')->withSession([
            AdminSessionVersion::SESSION_KEY => $admin->session_version,
        ])->patch(route('admin.products.update', $product), [
            'price' => '4000.00',
            'discount_percent' => 10,
        ])->assertRedirect()->assertSessionHas('status', 'Product updated.');

        $product->refresh();
        $this->assertSame('4000.00', $product->price);
        $this->assertSame('400.00', $product->discount_amount);
        $this->assertSame(360000, $product->finalPriceMinor());
        $this->get('/')->assertOk()->assertSee('10% off')->assertDontSee('25% off');
        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee('৳4,000')->assertSee('৳3,600')->assertSee('10% OFF');
    }

    public function test_black_and_white_stay_distinct_through_guest_cart_account_merge_and_order(): void
    {
        $product = $this->lamp([
            'variants' => [
                ['key' => 'black', 'label' => 'Black', 'available' => true, 'stock' => 3],
                ['key' => 'white', 'label' => 'White', 'available' => true, 'stock' => 2],
            ],
            'stock' => 5,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1, 'variant_key' => 'black'])->assertRedirect();
        $this->post(route('cart.add', $product), ['quantity' => 1, 'variant_key' => 'white'])->assertRedirect();
        $this->assertCount(2, session('cart'));

        $this->post(route('register.store'), [
            'name' => 'Variant Buyer',
            'email' => 'variant-buyer@example.test',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
        ])->assertRedirect(route('dashboard'));

        $cart = Cart::where('user_id', auth('web')->id())->sole();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'variant_key' => 'black', 'variant_label' => 'Black', 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'variant_key' => 'white', 'variant_label' => 'White', 'quantity' => 1]);

        $this->post(route('checkout.place'), $this->checkoutPayload('variant-order-key-0001'))
            ->assertRedirect(route('orders.index'));

        $order = Order::with('items')->sole();
        $this->assertSame('4998.00', $order->subtotal);
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertSame('4998.00', $order->total);
        $this->assertSame(['black', 'white'], $order->items->sortBy('variant_key')->pluck('variant_key')->all());
        $this->assertSame([2, 1], collect($product->fresh()->variants)->sortBy('key')->pluck('stock')->all());
    }

    public function test_buy_now_only_orders_the_selected_color_preserves_cart_and_replays_safely(): void
    {
        $lamp = $this->lamp();
        $other = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Other Lamp',
            'slug' => 'other-lamp',
            'price' => '900.00',
            'discount_amount' => '0.00',
            'stock' => 4,
            'status' => 'active',
        ]);
        $existingCart = [$other->id => 2];

        $response = $this->withSession(['cart' => $existingCart])->post(route('buy-now', $lamp), [
            'quantity' => 1,
            'variant_key' => 'black',
        ])->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $token = $query['buy_now'];

        $this->get(route('checkout', ['buy_now' => $token]))->assertOk()
            ->assertSee($lamp->name)->assertSee('Color: Black')->assertDontSee($other->name)
            ->assertSee('FREE / ৳0')->assertSee('৳2,499');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame($existingCart, session('cart'));

        $payload = [...$this->checkoutPayload('buy-now-order-key-0001'), 'buy_now_token' => $token];
        $this->post(route('checkout.place'), $payload)->assertRedirect(route('orders.index'));
        $this->post(route('checkout.place'), $payload)->assertRedirect(route('orders.index'));
        $this->post(route('checkout.place'), [
            ...$this->checkoutPayload('buy-now-order-key-0002'),
            'buy_now_token' => $token,
        ])->assertSessionHasErrors('cart');

        $order = Order::with('items')->sole();
        $this->assertSame('2499.00', $order->subtotal);
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertSame('2499.00', $order->total);
        $this->assertSame('black', $order->items->sole()->variant_key);
        $this->assertSame('Black', $order->items->sole()->variant_label);
        $this->assertSame($existingCart, session('cart'));
        $this->assertSame(4, $other->fresh()->stock);
        $this->assertSame(2, collect($lamp->fresh()->variants)->firstWhere('key', 'black')['stock']);
    }

    public function test_unavailable_or_missing_color_is_rejected_server_side(): void
    {
        $product = $this->lamp();

        $this->post(route('cart.add', $product), ['quantity' => 1, 'variant_key' => 'white'])
            ->assertSessionHasErrors('variant_key');
        $this->post(route('buy-now', $product), ['quantity' => 1])
            ->assertSessionHasErrors('variant_key');
        $this->assertNull(session('cart'));
        $this->assertDatabaseCount('orders', 0);
    }

    private function lamp(array $attributes = []): Product
    {
        return Product::create(array_replace([
            'category_id' => $this->category->id,
            'name' => 'LED Swing-Arm Desk Lamp',
            'slug' => 'series-x-articulated-lamp',
            'sku' => 'MAAT-LAMP-001',
            'description' => 'Focused, adjustable lighting for work and study.',
            'price' => '3332.00',
            'discount_amount' => '833.00',
            'stock' => 3,
            'specs' => ['Light source' => 'LED', 'Mounting' => 'Desk-edge clamp'],
            'variants' => [
                ['key' => 'black', 'label' => 'Black', 'available' => true, 'stock' => 3],
                ['key' => 'white', 'label' => 'White', 'available' => false, 'stock' => 0],
            ],
            'is_featured' => true,
            'status' => 'active',
        ], $attributes));
    }

    private function checkoutPayload(string $key): array
    {
        return [
            'name' => 'Buyer',
            'phone' => '01700000000',
            'district' => 'Sylhet',
            'address' => '123 Test Road',
            'checkout_attempt_key' => $key,
        ];
    }
}
