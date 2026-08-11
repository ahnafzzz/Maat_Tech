<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductsPageTest extends TestCase
{
    public function test_catalog_images_use_consistent_cover_styling(): void
    {
        $template = file_get_contents(base_path('resources/views/products.blade.php'));

        $this->assertStringContainsString('aspect-[4/3]', $template);
        $this->assertStringContainsString('object-cover object-center', $template);
    }
}
