<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\AdminSessionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCatalogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'visibility'], ['name' => 'Visibility']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Public Lamp',
            'slug' => 'public-lamp-'.fake()->unique()->numerify('####'),
            'sku' => 'PUBLIC-'.fake()->unique()->numerify('####'),
            'description' => 'Public catalog description',
            'price' => '100.00',
            'discount_amount' => '0.00',
            'stock' => 10,
            'status' => 'active',
            'is_featured' => true,
        ], $attributes));
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'visibility@example.test',
            'phone' => '01700000000',
            'password' => 'Test-password-42!',
        ], $attributes));
    }

    private function apiPayload(): array
    {
        return [
            'payment_method' => 'cod',
            'shipping_method' => 'pathao',
            'shipping_address' => [
                'name' => 'Visibility Buyer',
                'phone' => '01700000000',
                'city' => 'Dhaka',
                'address' => 'Visibility Road',
            ],
        ];
    }

    public function test_public_listings_sitemap_and_apis_include_only_active_products(): void
    {
        $active = $this->product(['name' => 'Visible Active Lamp']);
        $draft = $this->product(['name' => 'Hidden Draft Lamp', 'status' => 'draft']);
        $archived = $this->product(['name' => 'Hidden Archived Lamp', 'status' => 'archived']);

        $this->get('/')->assertOk()
            ->assertSee($active->name)->assertDontSee($draft->name)->assertDontSee($archived->name)
            ->assertSee('1 UNITS');
        $this->get('/products')->assertOk()
            ->assertSee($active->name)->assertDontSee($draft->name)->assertDontSee($archived->name);
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(route('products.show', $active->slug), false)
            ->assertDontSee(route('products.show', $draft->slug), false)
            ->assertDontSee(route('products.show', $archived->slug), false);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $draft->id])
            ->assertJsonMissing(['id' => $archived->id]);
        $this->getJson('/api/products/'.$active->id)->assertOk()->assertJsonPath('id', $active->id);
        $this->getJson('/api/products/'.$draft->id)->assertNotFound();
        $this->getJson('/api/products/'.$archived->id)->assertNotFound();
    }

    public function test_product_detail_hides_unpublished_records_related_products_and_unapproved_reviews(): void
    {
        $product = $this->product(['name' => 'Visible Detail Lamp']);
        $visibleRelated = $this->product(['name' => 'Visible Related Lamp']);
        $hiddenRelated = $this->product(['name' => 'Hidden Related Lamp', 'status' => 'draft']);
        $approvedBody = '<script>alert("approved review")</script>';
        Review::create([
            'product_id' => $product->id,
            'rating' => 5,
            'title' => 'Approved review title',
            'body' => $approvedBody,
            'is_approved' => true,
        ]);
        Review::create([
            'product_id' => $product->id,
            'rating' => 1,
            'title' => 'Unapproved secret title',
            'body' => 'Unapproved secret body',
            'is_approved' => false,
        ]);

        $response = $this->get('/products/'.$product->slug)->assertOk()
            ->assertSee($visibleRelated->name)
            ->assertDontSee($hiddenRelated->name)
            ->assertSee('Approved review title')
            ->assertSee('Rating: 5/5')
            ->assertDontSee('Unapproved secret title')
            ->assertDontSee('Unapproved secret body')
            ->assertDontSee('Rating: 1/5')
            ->assertSee($approvedBody)
            ->assertDontSee($approvedBody, false);
        $response->assertViewHas('product', fn (Product $viewProduct) => $viewProduct->reviews->count() === 1
            && (float) $viewProduct->reviews->avg('rating') === 5.0);

        $this->get('/products/'.$hiddenRelated->slug)->assertNotFound()->assertDontSee($hiddenRelated->name);
    }

    public function test_guest_and_customer_cannot_add_unpublished_products_to_cart_or_wishlist(): void
    {
        $active = $this->product(['name' => 'Existing Active Lamp']);
        $draft = $this->product(['name' => 'Rejected Draft Lamp', 'status' => 'draft']);

        $this->withSession(['cart' => [$active->id => 1], 'wishlist' => [$active->id]])
            ->post('/cart/add/'.$draft->id, ['quantity' => 2])->assertNotFound();
        $this->assertSame([$active->id => 1], session('cart'));
        $this->post('/wishlist/'.$draft->id)->assertNotFound();
        $this->assertSame([$active->id], session('wishlist'));

        $customer = $this->customer();
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $active->id, 'quantity' => 1]);
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $active->id]);

        $this->actingAs($customer, 'web')->post('/cart/add/'.$draft->id, ['quantity' => 2])->assertNotFound();
        $this->postJson('/api/cart', ['product_id' => $draft->id, 'quantity' => 2])->assertNotFound();
        $this->post('/wishlist/'.$draft->id)->assertNotFound();
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $active->id, 'quantity' => 1]);
        $this->assertDatabaseCount('wishlist_items', 1);
        $this->assertDatabaseHas('wishlist_items', ['wishlist_id' => $wishlist->id, 'product_id' => $active->id]);
    }

    public function test_guest_saved_items_become_generic_without_get_mutation_and_remain_removable(): void
    {
        $private = $this->product([
            'name' => 'Private Guest Lamp',
            'description' => 'Private guest description',
            'price' => '9876.54',
            'images' => ['products/private-guest.jpg'],
        ]);
        $deleted = $this->product(['name' => 'Deleted Guest Lamp']);
        $deletedId = $deleted->id;
        $deleted->delete();
        $private->update(['status' => 'draft']);
        $cartState = [$private->id => 2, $deletedId => 1];
        $wishlistState = [$private->id, $deletedId];

        $this->withSession(['cart' => $cartState, 'wishlist' => $wishlistState])->get('/cart')->assertOk()
            ->assertSee('Unavailable item')->assertDontSee('Private Guest Lamp')
            ->assertDontSee('Private guest description')->assertDontSee('products/private-guest.jpg');
        $this->assertSame($cartState, session('cart'));
        $this->get('/wishlist')->assertOk()->assertSee('Unavailable item')->assertDontSee('Private Guest Lamp');
        $this->assertSame($wishlistState, session('wishlist'));
        $this->get('/checkout')->assertRedirect(route('cart.index'))->assertSessionHasErrors('cart');
        $this->assertSame($cartState, session('cart'));

        $this->post('/cart/update/'.$private->id, ['quantity' => 3])->assertNotFound();
        $this->assertSame($cartState, session('cart'));
        $this->post('/cart/remove/'.$private->id)->assertRedirect();
        $this->assertSame([$deletedId => 1], session('cart'));
        $this->post('/wishlist/'.$private->id)->assertRedirect();
        $this->assertSame([$deletedId], session('wishlist'));
        $this->post('/cart/clear')->assertRedirect()->assertSessionMissing('cart');
        $this->post('/wishlist/'.$deletedId)->assertRedirect();
        $this->assertSame([], session('wishlist'));
    }

    public function test_customer_saved_items_and_api_use_generic_data_and_allow_removal_and_clear(): void
    {
        $customer = $this->customer();
        $private = $this->product([
            'name' => 'Private Customer Lamp',
            'description' => 'Private customer description',
            'price' => '7654.32',
            'images' => ['products/private-customer.jpg'],
        ]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $item = $cart->items()->create(['product_id' => $private->id, 'quantity' => 2]);
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $private->id]);
        $private->update(['status' => 'archived']);

        $this->actingAs($customer, 'web')->get('/cart')->assertOk()
            ->assertSee('Unavailable item')->assertDontSee('Private Customer Lamp')
            ->assertDontSee('Private customer description')->assertDontSee('products/private-customer.jpg');
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
        $this->get('/wishlist')->assertOk()->assertSee('Unavailable item')->assertDontSee('Private Customer Lamp');
        $this->assertDatabaseHas('wishlist_items', ['wishlist_id' => $wishlist->id, 'product_id' => $private->id]);

        $this->getJson('/api/cart')->assertOk()
            ->assertJsonPath('items.0.available', false)
            ->assertJsonPath('items.0.product', null)
            ->assertJsonPath('items.0.product_id', $private->id);
        $this->post('/cart/update/'.$private->id, ['quantity' => 3])->assertNotFound();
        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'quantity' => 2]);
        $this->post('/wishlist/'.$private->id)->assertRedirect();
        $this->assertDatabaseMissing('wishlist_items', ['wishlist_id' => $wishlist->id, 'product_id' => $private->id]);
        $this->post('/cart/clear')->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_login_merge_keeps_only_currently_published_guest_products(): void
    {
        $customer = $this->customer();
        $active = $this->product(['name' => 'Merge Active Lamp']);
        $draft = $this->product(['name' => 'Merge Draft Lamp', 'status' => 'draft']);
        $deleted = $this->product(['name' => 'Merge Deleted Lamp']);
        $deletedId = $deleted->id;
        $deleted->delete();

        $this->withSession([
            'cart' => [$active->id => 2, $draft->id => 3, $deletedId => 4],
            'wishlist' => [$active->id, $draft->id, $deletedId],
        ])->post('/login', ['email' => $customer->email, 'password' => 'Test-password-42!'])->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['product_id' => $active->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $draft->id]);
        $this->assertDatabaseHas('wishlist_items', ['product_id' => $active->id]);
        $this->assertDatabaseMissing('wishlist_items', ['product_id' => $draft->id]);
        $this->assertFalse(session()->has('cart'));
        $this->assertFalse(session()->has('wishlist'));
    }

    public function test_administrator_still_sees_unpublished_products(): void
    {
        $draft = $this->product(['name' => 'Admin Draft Lamp', 'status' => 'draft']);
        $archived = $this->product(['name' => 'Admin Archived Lamp', 'status' => 'archived']);
        $admin = Admin::create([
            'admin_id' => 'ADM-5100-X',
            'name' => 'Catalog Admin',
            'email' => 'catalog-admin@example.test',
            'password' => 'Test-password-42!',
            'status' => 'active',
            'session_version' => Str::random(64),
        ]);

        $this->actingAs($admin, 'admin')
            ->withSession([AdminSessionVersion::SESSION_KEY => $admin->session_version])
            ->get('/admin/products')->assertOk()->assertSee($draft->name)->assertSee($archived->name);
    }

    public function test_deactivation_preserves_order_snapshot_and_completed_checkout_replay(): void
    {
        $customer = $this->customer();
        $product = $this->product(['name' => 'Snapshot Lamp', 'sku' => 'SNAP-001']);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $headers = ['Idempotency-Key' => 'visibility-replay-key-0001'];

        $created = $this->actingAs($customer, 'web')->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'false');
        $orderId = $created->json('id');
        $product->update(['status' => 'draft', 'name' => 'Private Renamed Lamp']);

        $this->get('/products/'.$product->slug)->assertNotFound();
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->get('/orders')->assertOk()->assertSee('Snapshot Lamp')->assertDontSee('Private Renamed Lamp');
        $this->getJson('/api/orders/'.$orderId)->assertOk()
            ->assertJsonPath('items.0.product_name', 'Snapshot Lamp')
            ->assertJsonPath('items.0.product_sku', 'SNAP-001');
        $this->postJson('/api/orders', $this->apiPayload(), $headers)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('id', $orderId)
            ->assertJsonPath('items.0.product_name', 'Snapshot Lamp');
        $this->assertDatabaseCount('orders', 1);
    }
}
