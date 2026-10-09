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
            'price' => '3332.00',
            'discount_amount' => '833.00',
        ]);

        $response = $this->get('/')->assertOk()
            ->assertViewHas('heroProduct', fn (Product $product): bool => $product->is($lamp))
            ->assertSee('Fixture LED Swing-Arm Desk Lamp')
            ->assertSee('25% off')
            ->assertDontSee('BDT 2,499.00')
            ->assertSee(route('products.show', $lamp->slug), false)
            ->assertSee('data-showcase-model="fixture-lamp-model"', false)
            ->assertSee(asset('assets/models/fixture.json'), false)
            ->assertSee('Loading 3D preview')
            ->assertDontSee($unrelated->name);

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

        $this->assertSame(0, substr_count($response->getContent(), 'id="featured"'));
    }

    public function test_homepage_omits_empty_and_single_redundant_category_navigation(): void
    {
        $useful = Category::create(['name' => 'Task Lamps', 'slug' => 'task-lamps']);
        Category::create(['name' => 'Empty Category', 'slug' => 'empty-category']);
        $this->product($useful, ['slug' => 'only-published-product']);

        $this->get('/')->assertOk()
            ->assertViewHas('categories', fn ($categories): bool => $categories->count() === 1 && $categories->first()->is($useful))
            ->assertDontSee('>Categories<', false)
            ->assertDontSee('Empty Category');
    }

    public function test_product_linked_slideshow_uses_all_optimized_supplied_assets(): void
    {
        $category = Category::create(['name' => 'Desk Lamps', 'slug' => 'desk-lamps']);
        $product = $this->product($category, [
            'name' => 'LED Swing-Arm Desk Lamp',
            'slug' => 'series-x-articulated-lamp',
        ]);
        $slides = config('product-showcases.products.series-x-articulated-lamp.marketing_slides');

        $this->assertCount(9, $slides);
        $this->assertCount(6, array_filter($slides, fn (array $slide): bool => $slide['type'] === 'landscape'));
        $this->assertCount(3, array_filter($slides, fn (array $slide): bool => $slide['type'] === 'portrait'));
        foreach ($slides as $slide) {
            $path = public_path($slide['image']);
            $responsivePath = public_path(preg_replace('/\.webp$/', $slide['type'] === 'landscape' ? '-960.webp' : '-560.webp', $slide['image']));
            $this->assertFileExists($path);
            $this->assertFileExists($responsivePath);
            $this->assertLessThan(200_000, filesize($path));
            $this->assertLessThan(filesize($path), filesize($responsivePath));
        }

        $response = $this->get('/')->assertOk()
            ->assertSee('data-storefront-slideshow', false)
            ->assertSee('data-slideshow-interval="3000"', false)
            ->assertDontSee('data-slideshow-previous', false)
            ->assertDontSee('data-slideshow-next', false)
            ->assertSee('aspect-video', false)
            ->assertSee(route('products.show', $product->slug), false)
            ->assertDontSee('data-slideshow-pause', false)
            ->assertDontSee('data-slideshow-play', false);

        $this->assertSame(9, substr_count($response->getContent(), 'data-slideshow-slide'));
        $this->assertSame(9, substr_count($response->getContent(), 'data-slideshow-dot'));
    }

    public function test_extracted_showcase_assets_preserve_verified_model_contract(): void
    {
        $manifestPath = public_path('assets/models/desk-lamp/desk-lamp.4843375218151318.json');
        $binaryPath = public_path('assets/models/desk-lamp/desk-lamp.a45a18eea9197981.bin');
        $compressedPath = public_path('assets/models/desk-lamp/desk-lamp.3d8ef3d83ad3e741.bin.gz');
        $posterPath = public_path('assets/models/desk-lamp/desk-lamp-poster.webp');
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('32f54a79d4a3e6fe96fb8ec207c7c299bb9e15cf8df6ef11cc900dcfdf0639d6', $manifest['source']['sha256']);
        $this->assertSame(172, count($manifest['parts']));
        $this->assertSame(801426, $manifest['buffer']['vertexCount']);
        $this->assertSame(19234224, filesize($binaryPath));
        $this->assertSame($manifest['buffer']['sha256'], hash_file('sha256', $binaryPath));
        $this->assertSame('gzip', $manifest['buffer']['compressed']['format']);
        $this->assertSame(4369869, filesize($compressedPath));
        $this->assertSame($manifest['buffer']['compressed']['sha256'], hash_file('sha256', $compressedPath));
        $this->assertSame(file_get_contents($binaryPath), gzdecode((string) file_get_contents($compressedPath)));
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
