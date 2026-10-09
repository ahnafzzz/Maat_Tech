<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderLifecycleService;
use App\Services\ProductWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'lighting'], ['name' => 'Lighting']);

        return Product::create(array_replace([
            'category_id' => $category->id,
            'name' => 'Reserved Lamp',
            'slug' => 'reserved-lamp-'.fake()->unique()->numerify('####'),
            'sku' => 'RES-'.fake()->unique()->numerify('####'),
            'price' => '3332.00',
            'discount_amount' => '833.00',
            'stock' => 3,
            'status' => 'active',
            'variants' => [
                ['key' => 'black', 'label' => 'Black', 'available' => true, 'stock' => 1],
                ['key' => 'white', 'label' => 'White', 'available' => true, 'stock' => 2],
            ],
        ], $attributes));
    }

    private function order(Product $product, array $attributes = []): Order
    {
        $order = Order::create(array_replace([
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
            'subtotal' => '4998.00',
            'shipping_fee' => '0.00',
            'total' => '4998.00',
            'shipping_address' => ['name' => 'Buyer', 'phone' => '01700000000', 'city' => 'Dhaka', 'address' => 'Test Road 1'],
            'placed_at' => now(),
            'expires_at' => now()->addHours(48),
        ], $attributes));
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'variant_key' => 'black',
            'variant_label' => 'Black',
            'quantity' => 2,
            'unit_price' => '2499.00',
        ]);

        return $order;
    }

    public function test_cancellation_restores_reserved_variant_stock_exactly_once(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $service = app(OrderLifecycleService::class);

        $cancelled = $service->transition($order, 'cancelled');
        $variants = collect($product->fresh()->purchasableVariants())->keyBy('key');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->stock_released_at);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNull($cancelled->expires_at);
        $this->assertSame(3, $variants['black']['stock']);
        $this->assertSame(5, $product->fresh()->stock);

        $service->transition($cancelled, 'cancelled');
        $this->assertSame(3, collect($product->fresh()->purchasableVariants())->keyBy('key')['black']['stock']);
    }

    public function test_confirmation_does_not_imply_shipment_and_refund_after_shipment_does_not_restock(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $service = app(OrderLifecycleService::class);

        $confirmed = $service->transition($order, 'processing');
        $this->assertSame('processing', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNull($confirmed->expires_at);

        $shipped = $service->transition($confirmed, 'shipped', 'TRACK-001');
        $refunded = $service->transition($shipped, 'refunded');
        $this->assertSame('refunded', $refunded->status);
        $this->assertNull($refunded->stock_released_at);
        $this->assertSame(1, collect($product->fresh()->purchasableVariants())->keyBy('key')['black']['stock']);
    }

    public function test_expiry_command_cancels_only_expired_pending_orders_idempotently(): void
    {
        $expiredProduct = $this->product(['slug' => 'expired-product']);
        $futureProduct = $this->product(['slug' => 'future-product']);
        $expired = $this->order($expiredProduct, ['expires_at' => now()->subMinute()]);
        $future = $this->order($futureProduct, ['expires_at' => now()->addHour()]);

        $this->artisan('orders:expire-pending')->assertSuccessful()->expectsOutput('Expired 1 pending order(s).');
        $this->assertSame('cancelled', $expired->fresh()->status);
        $this->assertSame('pending', $future->fresh()->status);
        $this->artisan('orders:expire-pending')->assertSuccessful()->expectsOutput('Expired 0 pending order(s).');
        $this->assertSame(3, collect($expiredProduct->fresh()->purchasableVariants())->keyBy('key')['black']['stock']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $product = $this->product();
        $order = $this->order($product);

        $this->expectException(ValidationException::class);
        app(OrderLifecycleService::class)->transition($order, 'shipped');
    }

    public function test_active_reserved_product_cannot_be_deleted(): void
    {
        $product = $this->product();
        $this->order($product);

        try {
            app(ProductWriteService::class)->delete($product);
            $this->fail('Expected active inventory reservation to block product deletion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
