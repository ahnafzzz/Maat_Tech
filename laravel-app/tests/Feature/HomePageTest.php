<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_homepage_template_has_a_single_featured_catalog_section(): void
    {
        $template = file_get_contents(base_path('resources/views/home.blade.php'));

        $this->assertStringContainsString('CURATED_CATALOG', $template);
        $this->assertSame(1, substr_count($template, 'id="featured"'));
        $this->assertStringContainsString('object-cover object-center', $template);
    }
}
