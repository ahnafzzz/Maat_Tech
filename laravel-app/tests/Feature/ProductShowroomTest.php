<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShowroomTest extends TestCase
{
    use RefreshDatabase;

    public function test_associated_product_gets_showroom_and_preserves_real_shopping_content(): void
    {
        config(['product-showcases.products' => [
            'linked-lamp' => [
                'model_id' => 'fixture-lamp-model',
                'manifest' => 'assets/models/fixture.json',
                'poster' => 'assets/models/fixture.webp',
            ],
        ]]);
        $product = $this->product([
            'name' => 'Fixture Swing-Arm Lamp',
            'slug' => 'linked-lamp',
            'price' => '2350.00',
            'stock' => 7,
            'images' => ['products/lamp-front.webp', 'products/lamp-side.webp'],
        ]);

        $response = $this->get(route('products.show', $product->slug))->assertOk()
            ->assertViewHas('productShowcase', fn (array $showcase): bool => $showcase['model_id'] === 'fixture-lamp-model')
            ->assertSee('data-product-showroom', false)
            ->assertSee('data-showroom-model="fixture-lamp-model"', false)
            ->assertSee(asset('assets/models/fixture.json'), false)
            ->assertSee('Fixture Swing-Arm Lamp')
            ->assertSee('BDT 2,350.00')
            ->assertSee('In stock (7 available)')
            ->assertSee('name="quantity"', false)
            ->assertSee('id="product-purchase-form"', false)
            ->assertSee('form="product-purchase-form"', false)
            ->assertSee('data-submit-once', false)
            ->assertSee('Add to Cart')
            ->assertSee('storage/products/lamp-front.webp', false)
            ->assertSee('storage/products/lamp-side.webp', false)
            ->assertSee('Visual preview only')
            ->assertSee('Explore Engineering View')
            ->assertSee('Auto rotate complete lamp')
            ->assertSee('Separate clamp components')
            ->assertSee('Show external power leads for inspection')
            ->assertSee('Renderer simulation intensity')
            ->assertSee('data-showroom-input="selection"', false)
            ->assertSee('data-showroom-action="camera"', false)
            ->assertSee('Customer Reviews')
            ->assertDontSee('verified buyer', false)
            ->assertDontSee('Bulk pricing');

        $this->assertSame(1, substr_count($response->getContent(), 'data-product-showroom'));
        $this->assertSame(1, substr_count($response->getContent(), 'action="'.route('cart.add', $product).'"'));
    }

    public function test_unassociated_product_keeps_image_gallery_without_loading_model(): void
    {
        config(['product-showcases.products' => []]);
        $product = $this->product([
            'slug' => 'ordinary-product',
            'image' => 'products/ordinary.webp',
        ]);

        $this->get(route('products.show', $product->slug))->assertOk()
            ->assertSee(asset('storage/products/ordinary.webp'), false)
            ->assertDontSee('data-product-showroom', false)
            ->assertDontSee('data-showroom-manifest', false)
            ->assertDontSee('Explore Engineering View');
    }

    public function test_homepage_showcase_remains_navigation_only(): void
    {
        config(['product-showcases.products' => [
            'linked-lamp' => [
                'model_id' => 'fixture-lamp-model',
                'manifest' => 'assets/models/fixture.json',
                'poster' => 'assets/models/fixture.webp',
            ],
        ]]);
        $this->product(['slug' => 'linked-lamp']);

        $this->get('/')->assertOk()
            ->assertSee('data-product-showcase', false)
            ->assertDontSee('data-product-showroom', false)
            ->assertDontSee('Explore Engineering View')
            ->assertDontSee('data-showroom-action', false);
    }

    public function test_storefront_navigation_hides_sitemap_but_xml_endpoint_remains_available(): void
    {
        $this->product([]);

        $this->get('/')->assertOk()
            ->assertDontSee('>Sitemap<', false)
            ->assertDontSee('href="'.route('sitemap').'"', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    private function product(array $attributes): Product
    {
        $category = Category::firstOrCreate(['slug' => 'desk-lamps'], ['name' => 'Desk Lamps']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Published Product',
            'slug' => 'published-product',
            'description' => 'Published product description.',
            'price' => '100.00',
            'discount_amount' => '0.00',
            'stock' => 5,
            'status' => 'active',
            'is_featured' => true,
        ], $attributes));
    }
}
