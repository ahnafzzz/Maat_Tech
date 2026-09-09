<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminSessionVersion;
use App\Services\ProductMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductWriteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->category = Category::create(['name' => 'Lighting', 'slug' => 'lighting']);
    }

    private function authenticateAdmin(?string $sessionVersion = null): Admin
    {
        $admin = Admin::create([
            'admin_id' => 'ADM-1500-A',
            'name' => 'Catalog Admin',
            'email' => 'catalog-admin@example.test',
            'password' => 'Admin-Password-42!',
            'status' => 'active',
            'session_version' => Str::random(64),
        ]);
        $this->actingAs($admin, 'admin')->withSession([
            AdminSessionVersion::SESSION_KEY => $sessionVersion ?? $admin->session_version,
        ]);

        return $admin;
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_replace([
            'category_id' => $this->category->id,
            'name' => 'Existing Lamp',
            'slug' => 'existing-lamp-'.fake()->unique()->numerify('####'),
            'sku' => 'EXISTING-'.fake()->unique()->numerify('####'),
            'description' => 'Existing description',
            'price' => '100.00',
            'compare_at_price' => '120.00',
            'discount_amount' => '10.00',
            'stock' => 10,
            'status' => 'active',
            'is_featured' => true,
            'images' => [],
        ], $attributes));
    }

    private function createPayload(string $name, ?UploadedFile $image = null): array
    {
        return array_filter([
            'category_id' => $this->category->id,
            'name' => $name,
            'price' => '125.50',
            'compare_at_price' => '150.00',
            'discount_amount' => '5.25',
            'stock' => 7,
            'status' => 'draft',
            'description' => 'Synthetic product',
            'images' => $image ? [$image] : null,
        ], fn ($value) => $value !== null);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
        );
    }

    private function productSnapshot(Product $product): array
    {
        return Product::whereKey($product->id)->firstOrFail()->getRawOriginal();
    }

    public function test_web_and_api_create_use_the_same_catalog_and_media_contract(): void
    {
        $this->authenticateAdmin();

        $this->post(route('admin.products.store'), $this->createPayload(
            'Web Lamp',
            $this->image('web-lamp.png')
        ))->assertRedirect()->assertSessionHas('status', 'Product created.');

        $apiResponse = $this->postJson('/api/products', [
            ...$this->createPayload('API Lamp'),
            'slug' => 'api-lamp',
            'sku' => 'API-LAMP-001',
        ])->assertCreated()->assertJsonPath('media_cleanup_pending', false);

        $web = Product::where('name', 'Web Lamp')->sole();
        $api = Product::findOrFail($apiResponse->json('id'));
        $this->assertMatchesRegularExpression('/^web-lamp-[a-zA-Z0-9]{12}$/', $web->slug);
        $this->assertStringStartsWith('ML-', $web->sku);
        $this->assertSame('125.50', $web->price);
        $this->assertSame('5.25', $web->discount_amount);
        $this->assertSame('draft', $web->status);
        $this->assertCount(1, $web->images);
        Storage::disk('public')->assertExists($web->images[0]);
        $this->assertSame('api-lamp', $api->slug);
        $this->assertSame('API-LAMP-001', $api->sku);
        $this->assertSame('125.50', $api->price);
        $this->assertSame('draft', $api->status);
    }

    public function test_partial_updates_retain_omitted_values_and_apply_explicit_nulls(): void
    {
        $this->authenticateAdmin();
        $product = $this->product();

        $this->putJson('/api/products/'.$product->id, [
            'description' => null,
            'compare_at_price' => null,
            'discount_amount' => null,
            'sku' => null,
        ])->assertOk();

        $product->refresh();
        $this->assertSame('Existing Lamp', $product->name);
        $this->assertSame('100.00', $product->price);
        $this->assertSame('active', $product->status);
        $this->assertNull($product->description);
        $this->assertNull($product->compare_at_price);
        $this->assertSame('0.00', $product->discount_amount);
        $this->assertNull($product->sku);

        $this->patch(route('admin.products.update', $product), ['description' => ''])
            ->assertRedirect();
        $this->assertNull($product->fresh()->description);

        $before = $product->fresh()->toArray();
        $this->putJson('/api/products/'.$product->id, ['name' => null])->assertUnprocessable();
        $this->assertSame($before, $product->fresh()->toArray());

        $legacy = $this->product(['image' => 'products/legacy.jpg', 'images' => []]);
        $this->putJson('/api/products/'.$legacy->id, ['name' => 'Renamed Legacy Product'])->assertOk();
        $legacy->refresh();
        $this->assertSame('products/legacy.jpg', $legacy->image);
        $this->assertSame([], $legacy->images);
    }

    public static function invalidCatalogValues(): array
    {
        return [
            'status' => [['status' => 'hidden'], 'status'],
            'category' => [['category_id' => 999999], 'category_id'],
            'price precision' => [['price' => '1.001'], 'price'],
            'compare below price' => [['compare_at_price' => '99.99'], 'compare_at_price'],
            'discount above price' => [['discount_amount' => '100.01'], 'discount_amount'],
            'negative stock' => [['stock' => -1], 'stock'],
            'long name' => [['name' => 'x'.str_repeat('y', 255)], 'name'],
        ];
    }

    #[DataProvider('invalidCatalogValues')]
    public function test_web_and_api_reject_invalid_catalog_values_without_mutation(array $invalid, string $field): void
    {
        $this->authenticateAdmin();
        $webProduct = $this->product();
        $apiProduct = $this->product();
        $webBefore = $this->productSnapshot($webProduct);
        $apiBefore = $this->productSnapshot($apiProduct);

        $this->patch(route('admin.products.update', $webProduct), $invalid)
            ->assertRedirect()->assertSessionHasErrors($field);
        $this->putJson('/api/products/'.$apiProduct->id, $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame($webBefore, $this->productSnapshot($webProduct));
        $this->assertSame($apiBefore, $this->productSnapshot($apiProduct));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_duplicate_identifiers_reject_other_products_but_allow_the_actual_target(): void
    {
        $this->authenticateAdmin();
        $first = $this->product(['slug' => 'first-slug', 'sku' => 'FIRST-SKU']);
        $second = $this->product(['slug' => 'second-slug', 'sku' => 'SECOND-SKU']);
        $before = $this->productSnapshot($second);

        $this->putJson('/api/products/'.$second->id, ['slug' => $first->slug, 'sku' => $first->sku])
            ->assertUnprocessable()->assertJsonValidationErrors(['slug', 'sku']);
        $this->assertSame($before, $this->productSnapshot($second));

        $this->putJson('/api/products/'.$second->id, ['slug' => $second->slug, 'sku' => $second->sku])
            ->assertOk();
    }

    public function test_invalid_uploads_and_foreign_media_removal_leave_database_and_files_unchanged(): void
    {
        $this->authenticateAdmin();
        $ownPath = 'products/1/images/own.jpg';
        $foreignPath = 'products/2/images/foreign.jpg';
        Storage::disk('public')->put($ownPath, 'own');
        Storage::disk('public')->put($foreignPath, 'foreign');
        $product = $this->product(['images' => [$ownPath], 'image' => $ownPath]);
        $other = $this->product(['images' => [$foreignPath], 'image' => $foreignPath]);
        $before = $this->productSnapshot($product);

        $this->putJson('/api/products/'.$product->id, [
            'images' => [UploadedFile::fake()->create('payload.php', 2, 'application/x-php')],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->patch(route('admin.products.update', $product), [
            'images' => [UploadedFile::fake()->create('payload.svg', 2, 'image/svg+xml')],
        ])->assertRedirect()->assertSessionHasErrors('images.0');
        $this->patch(route('admin.products.update', $product), ['remove_images' => [$foreignPath]])
            ->assertRedirect()->assertSessionHasErrors('remove_images');
        $this->putJson('/api/products/'.$product->id, ['remove_images' => [$foreignPath]])
            ->assertUnprocessable()->assertJsonValidationErrors('remove_images');
        $this->putJson('/api/products/'.$product->id, ['image_path' => '/var/www/foreign.jpg'])
            ->assertUnprocessable()->assertJsonValidationErrors('image_path');
        $this->patch(route('admin.products.update', $product), ['video_url' => 'https://example.test/video.mp4'])
            ->assertRedirect()->assertSessionHasErrors('video_url');

        $this->assertSame($before, $this->productSnapshot($product));
        Storage::disk('public')->assertExists([$ownPath, $foreignPath]);
        $this->assertDatabaseHas('products', ['id' => $other->id]);
    }

    public function test_web_and_api_can_replace_or_remove_only_their_own_media(): void
    {
        $this->authenticateAdmin();
        $webOld = 'products/web/images/old.jpg';
        $apiOld = 'products/api/images/old.jpg';
        $webVideo = 'products/web/video/old.mp4';
        Storage::disk('public')->put($webOld, 'web-old');
        Storage::disk('public')->put($apiOld, 'api-old');
        Storage::disk('public')->put($webVideo, 'web-video');
        $web = $this->product(['images' => [$webOld], 'image' => $webOld, 'video_path' => $webVideo]);
        $api = $this->product(['images' => [$apiOld], 'image' => $apiOld]);

        $this->patch(route('admin.products.update', $web), [
            'remove_images' => [$webOld],
            'remove_video' => true,
            'images' => [$this->image('web-new.png')],
        ])->assertRedirect();
        $this->call('PUT', '/api/products/'.$api->id, ['remove_images' => [$apiOld]], [], [
            'images' => [$this->image('api-new.png')],
        ], ['HTTP_ACCEPT' => 'application/json'])->assertOk();

        $web->refresh();
        $api->refresh();
        Storage::disk('public')->assertMissing($webOld);
        Storage::disk('public')->assertMissing($webVideo);
        Storage::disk('public')->assertExists($web->images[0]);
        $this->assertNull($web->video_path);
        Storage::disk('public')->assertMissing($apiOld);
        Storage::disk('public')->assertExists($api->images[0]);
    }

    public function test_database_failure_after_upload_removes_new_file_and_preserves_existing_media(): void
    {
        $this->authenticateAdmin();
        $oldPath = 'products/existing/images/old.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $product = $this->product(['images' => [$oldPath], 'image' => $oldPath]);
        $before = $this->productSnapshot($product);
        Product::updating(function (Product $updating) use ($product): void {
            if ($updating->is($product)) {
                throw new RuntimeException('Injected product database failure');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->call('PUT', '/api/products/'.$product->id, ['name' => 'Must Roll Back'], [], [
                'images' => [$this->image('new.png')],
            ], ['HTTP_ACCEPT' => 'application/json']);
            $this->fail('Expected the injected database failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected product database failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->productSnapshot($product));
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
    }

    public function test_cleanup_failure_reports_pending_cleanup_without_reverting_committed_update(): void
    {
        $this->authenticateAdmin();
        $oldPath = 'products/existing/images/old.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $product = $this->product(['images' => [$oldPath], 'image' => $oldPath]);
        $mediaStorage = Mockery::mock(ProductMediaStorage::class);
        $mediaStorage->shouldReceive('delete')->once()->with($oldPath)->andReturnFalse();
        $this->app->instance(ProductMediaStorage::class, $mediaStorage);

        $this->putJson('/api/products/'.$product->id, [
            'name' => 'Committed Name',
            'remove_images' => [$oldPath],
        ])->assertOk()->assertJsonPath('media_cleanup_pending', true);

        $product->refresh();
        $this->assertSame('Committed Name', $product->name);
        $this->assertSame([], $product->images);
        $this->assertNull($product->image);
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_product_deletion_cleans_unreferenced_media_and_preserves_checkout_replay_snapshots(): void
    {
        $this->authenticateAdmin();
        $mediaPath = 'products/snapshot/images/lamp.jpg';
        Storage::disk('public')->put($mediaPath, 'snapshot-media');
        $product = $this->product([
            'name' => 'Historical Lamp',
            'sku' => 'HISTORY-001',
            'images' => [$mediaPath],
            'image' => $mediaPath,
        ]);
        $customer = User::factory()->create([
            'email' => 'history-product@example.test',
            'phone' => '01700000000',
        ]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $payload = [
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
            'shipping_address' => [
                'name' => 'History Buyer',
                'phone' => '01700000000',
                'city' => 'Dhaka',
                'address' => 'History Road',
            ],
        ];
        $headers = ['Idempotency-Key' => 'product-delete-replay-0001'];
        $orderId = $this->actingAs($customer, 'web')->postJson('/api/orders', $payload, $headers)
            ->assertCreated()->json('id');

        $this->deleteJson('/api/products/'.$product->id)
            ->assertOk()->assertJsonPath('media_cleanup_pending', false);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($mediaPath);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => null,
            'product_name' => 'Historical Lamp',
            'product_sku' => 'HISTORY-001',
        ]);
        $this->postJson('/api/orders', $payload, $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('id', $orderId)
            ->assertJsonPath('items.0.product_name', 'Historical Lamp');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_guest_and_stale_admin_writes_cannot_change_catalog_or_storage(): void
    {
        $payload = $this->createPayload('Unauthorized', $this->image('unauthorized.png'));
        $this->post(route('admin.products.store'), $payload)->assertRedirect(route('admin.login'));
        $this->postJson('/api/products', $this->createPayload('Unauthorized API'))->assertUnauthorized();
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $admin = $this->authenticateAdmin('stale-session-version');
        $this->postJson('/api/products', $this->createPayload('Stale API'))->assertUnauthorized();
        $this->actingAs($admin, 'admin')->withSession([AdminSessionVersion::SESSION_KEY => 'stale-session-version']);
        $this->post(route('admin.products.store'), $this->createPayload('Stale Web'))
            ->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
