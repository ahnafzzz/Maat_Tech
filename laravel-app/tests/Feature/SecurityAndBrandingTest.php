<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAndBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_canonical_redirect_preserves_path_and_query_and_rejects_unknown_hosts(): void
    {
        $previous = $this->app['env'];
        $this->app['env'] = 'production';
        config()->set('site.canonical_host', 'www.maattechbd.store');
        config()->set('site.redirect_hosts', ['maattechbd.store']);

        try {
            $this->get('http://maattechbd.store/products?color=black&page=2')
                ->assertStatus(301)
                ->assertRedirect('https://www.maattechbd.store/products?color=black&page=2');
            $this->get('http://www.maattechbd.store/products?color=white')
                ->assertStatus(301)
                ->assertRedirect('https://www.maattechbd.store/products?color=white');
            $this->get('https://untrusted.example/products')->assertBadRequest();
        } finally {
            $this->app['env'] = $previous;
        }
    }

    public function test_security_headers_are_present_and_private_pages_are_not_cacheable(): void
    {
        $public = $this->get('/')->assertOk();
        $public->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
        $csp = $public->headers->get('Content-Security-Policy');
        $this->assertIsString($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $public->getContent());
        $this->assertStringNotContainsString('unpkg.com', $public->getContent());

        $user = User::factory()->create();
        $private = $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertStringContainsString('no-store', (string) $private->headers->get('Cache-Control'));
        $private->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_customer_pages_use_official_branding_requested_labels_and_no_admin_link(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('MAAT Technologies BD', false)
            ->assertSee('images/brand/maat-tech-logo', false)
            ->assertDontSee('/admin/login', false);
        $this->get('/register')->assertOk()
            ->assertSee('MAAT Technologies BD', false)
            ->assertSee('images/brand/maat-tech-logo', false)
            ->assertDontSee('/admin/login', false);
        $this->get('/products')->assertOk()->assertSee('All products')->assertDontSee('precision Unit');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Profile')->assertSee('Wishlist')->assertSee('Cart')->assertSee('All orders')
            ->assertSee('Order status')->assertSee('Logout')
            ->assertDontSee('User_Dashboard v1.0')->assertDontSee('cart_units')
            ->assertDontSee('wishlist_units')->assertDontSee('pending_shipments')
            ->assertDontSee('/admin/login', false);
        $this->get('/orders')->assertOk()->assertDontSee('fulfilment log');
    }

    public function test_direct_admin_login_remains_available_but_protected(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('ADMIN_ACCESS');
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
