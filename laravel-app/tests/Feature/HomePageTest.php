<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicitly_associated_product_drives_showcase_identity_price_and_links(): void
    {
        config(['product-showcases.products' => [
            'linked-lamp' => [
                'model_id' => 'fixture-lamp-model',
                'manifest' => 'assets/models/fixture.json',
                'poster' => 'assets/models/fixture.webp',
            ],
        ]]);
        $category = Category::create(['name' => 'Desk Lamps', 'slug' => 'desk-lamps']);
        $unrelated = null;
        foreach (range(1, 3) as $index) {
            $unrelated = $this->product($category, [
                'name' => 'Unrelated Featured Product '.$index,
                'slug' => 'unrelated-'.$index,
                'price' => '999.00',
            ]);
        }
        $lamp = $this->product($category, [
            'name' => 'Fixture LED Swing-Arm Desk Lamp',
            'slug' => 'linked-lamp',
            'price' => '2350.00',
        ]);

        $response = $this->get('/')->assertOk()
            ->assertViewHas('heroProduct', fn (Product $product): bool => $product->is($lamp))
            ->assertSee('Fixture LED Swing-Arm Desk Lamp')
            ->assertSee('BDT 2,350.00')
            ->assertSee(route('products.show', $lamp->slug), false)
            ->assertSee('data-showcase-model="fixture-lamp-model"', false)
            ->assertSee(asset('assets/models/fixture.json'), false)
            ->assertSee('Loading 3D preview')
            ->assertSee($unrelated->name);

        $this->assertStringNotContainsString(
            'data-showcase-model="fixture-lamp-model"',
            $this->get('/products/'.$unrelated->slug)->getContent()
        );
        $this->assertSame(1, substr_count($response->getContent(), 'data-showcase-model="fixture-lamp-model"'));
    }

    public function test_unassociated_featured_product_uses_its_own_image_and_link_without_model(): void
    {
        config(['product-showcases.products' => []]);
        $category = Category::create(['name' => 'Task Lamps', 'slug' => 'task-lamps']);
        $product = $this->product($category, [
            'name' => 'Image Only Task Lamp',
            'slug' => 'image-only-task-lamp',
            'image' => 'products/image-only.webp',
        ]);

        $response = $this->get('/')->assertOk()
            ->assertViewHas('heroProduct', fn (Product $hero): bool => $hero->is($product))
            ->assertSee(asset('storage/products/image-only.webp'), false)
            ->assertSee(route('products.show', $product->slug), false)
            ->assertDontSee('data-showcase-manifest', false)
            ->assertSee('Product image');

        $this->assertSame(1, substr_count($response->getContent(), 'id="featured"'));
    }

    public function test_extracted_showcase_assets_preserve_verified_model_contract(): void
    {
        $manifestPath = public_path('assets/models/desk-lamp/desk-lamp.de824f25cf33f9e6.json');
        $binaryPath = public_path('assets/models/desk-lamp/desk-lamp.a45a18eea9197981.bin');
        $posterPath = public_path('assets/models/desk-lamp/desk-lamp-poster.webp');
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('32f54a79d4a3e6fe96fb8ec207c7c299bb9e15cf8df6ef11cc900dcfdf0639d6', $manifest['source']['sha256']);
        $this->assertSame(172, count($manifest['parts']));
        $this->assertSame(801426, $manifest['buffer']['vertexCount']);
        $this->assertSame(19234224, filesize($binaryPath));
        $this->assertSame($manifest['buffer']['sha256'], hash_file('sha256', $binaryPath));
        $this->assertSame(['Cable.'], $manifest['profiles']['showcase']['hiddenPartPrefixes']);
        $this->assertFalse($manifest['profiles']['showcase']['proceduralExternalLead']);
        $this->assertContains('Head.diffuser', array_column($manifest['parts'], 'name'));
        $this->assertContains('Controller.lower_shell', array_column($manifest['parts'], 'name'));
        $this->assertContains('USB.hollow_metal_shell', array_column($manifest['parts'], 'name'));
        $this->assertFileExists($posterPath);
        $this->assertGreaterThan(0, filesize($posterPath));
    }

    private function product(Category $category, array $attributes): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Featured Product',
            'slug' => 'featured-product',
            'description' => 'Current published product description.',
            'price' => '100.00',
            'discount_amount' => '0.00',
            'stock' => 5,
            'status' => 'active',
            'is_featured' => true,
        ], $attributes));
    }
}
