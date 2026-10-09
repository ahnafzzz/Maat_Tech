<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Banner;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Services\AdminSessionVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_admin_can_update_structured_storefront_settings_without_html(): void
    {
        $this->authenticateAdmin();
        $settings = StorefrontSetting::current();
        $payload = $this->settingsPayload($settings);
        $payload['site_name'] = 'Northstar Lighting';
        $payload['hero_heading'] = 'Light your best work';
        $payload['logo'] = $this->image('logo.png');

        $this->get(route('admin.storefront'))->assertOk()
            ->assertSee('Storefront CMS')
            ->assertSee('No raw HTML is accepted.');
        $this->patch(route('admin.storefront.settings.update'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertSame('Northstar Lighting', $settings->site_name);
        $this->assertSame('Light your best work', $settings->hero_heading);
        $this->assertNotNull($settings->logo_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        $this->get('/')->assertOk()->assertSee('Northstar Lighting')->assertSee('Light your best work');
    }

    public function test_content_page_body_and_cards_are_editable_and_always_escaped(): void
    {
        $this->authenticateAdmin();
        $page = StorefrontPage::where('slug', 'about')->firstOrFail();

        $this->patch(route('admin.storefront.pages.update', $page), [
            'eyebrow' => 'OUR_STORY',
            'title' => 'Our workshop',
            'meta_title' => 'Workshop story',
            'meta_description' => 'A structured editable page.',
            'body_text' => "First paragraph.\n\n<script>alert('unsafe')</script>",
            'items' => [['title' => 'Built locally', 'text' => 'Carefully assembled and tested.']],
            'is_visible' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/about')->assertOk()
            ->assertSee('Our workshop')
            ->assertSee('Built locally')
            ->assertSee('&lt;script&gt;alert', false)
            ->assertDontSee("<script>alert('unsafe')</script>", false);
    }

    public function test_navigation_rejects_executable_urls(): void
    {
        $this->authenticateAdmin();

        $this->post(route('admin.storefront.navigation.store'), [
            'label' => 'Unsafe',
            'url' => 'javascript:alert(1)',
            'location' => 'header',
            'column' => 1,
            'sort_order' => 1,
            'is_active' => 1,
            'open_new_tab' => 0,
        ])->assertSessionHasErrors('url');
    }

    public function test_admin_can_upload_and_publish_a_homepage_slide(): void
    {
        $this->authenticateAdmin();

        $this->post(route('admin.storefront.slides.store'), [
            'title' => 'Autumn workspace',
            'eyebrow' => 'NEW_COLLECTION',
            'subtitle' => 'A focused workspace for long evenings.',
            'alt_text' => 'A lamp on an autumn workspace',
            'link_url' => '/products',
            'button_label' => 'Shop now',
            'layout' => 'split',
            'sort_order' => 5,
            'is_active' => 1,
            'image' => $this->image('workspace.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $banner = Banner::where('title', 'Autumn workspace')->firstOrFail();
        Storage::disk('public')->assertExists($banner->image_path);
        $this->get('/')->assertOk()->assertSee('Autumn workspace')->assertSee('NEW_COLLECTION');
    }

    private function authenticateAdmin(): Admin
    {
        $admin = Admin::create([
            'admin_id' => 'ADM-1600-C',
            'name' => 'CMS Admin',
            'email' => 'cms-admin@example.test',
            'password' => 'Admin-Password-42!',
            'status' => 'active',
            'session_version' => Str::random(64),
            'two_factor_enabled' => true,
        ]);
        $this->actingAs($admin, 'admin')->withSession([
            AdminSessionVersion::SESSION_KEY => $admin->session_version,
        ]);

        return $admin;
    }

    private function settingsPayload(StorefrontSetting $settings): array
    {
        return $settings->only([
            'site_name', 'tagline', 'logo_alt', 'default_meta_description', 'support_email',
            'phone_display', 'whatsapp_number', 'address', 'business_hours', 'facebook_url',
            'instagram_url', 'home_meta_title', 'home_meta_description', 'hero_badge',
            'hero_heading', 'hero_copy', 'hero_primary_label', 'hero_secondary_label', 'hero_note',
            'featured_product_id', 'slideshow_enabled', 'shop_label', 'account_label', 'sign_in_label',
            'wishlist_enabled', 'cart_enabled', 'footer_copyright', 'footer_status',
            'footer_support_label', 'whatsapp_cta_label', 'accent_color', 'primary_color',
            'background_color', 'panel_color',
        ]);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }
}
