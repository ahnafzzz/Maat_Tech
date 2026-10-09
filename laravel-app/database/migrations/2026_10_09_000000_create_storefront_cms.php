<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('site_name')->default('MAAT Technologies BD');
            $table->string('tagline')->default('PRECISION LIGHTING');
            $table->string('logo_path')->nullable();
            $table->string('logo_alt')->default('MAAT Technologies BD logo');
            $table->text('default_meta_description')->nullable();
            $table->string('support_email')->nullable();
            $table->string('phone_display', 50)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('business_hours')->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('home_meta_title')->default('Featured Lighting');
            $table->text('home_meta_description')->nullable();
            $table->string('hero_badge')->default('Featured lighting');
            $table->string('hero_heading')->nullable();
            $table->text('hero_copy')->nullable();
            $table->string('hero_primary_label')->default('Explore the lamp');
            $table->string('hero_secondary_label')->default('Shop Lamps');
            $table->string('hero_note')->default('3D product preview · presentation view');
            $table->foreignId('featured_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('slideshow_enabled')->default(true);
            $table->string('shop_label')->default('Shop Lamps');
            $table->string('account_label')->default('Account');
            $table->string('sign_in_label')->default('Sign in');
            $table->boolean('wishlist_enabled')->default(true);
            $table->boolean('cart_enabled')->default(true);
            $table->string('footer_copyright')->default('2026 MAAT Technologies BD');
            $table->string('footer_status')->default('Storefront online');
            $table->string('footer_support_label')->default('Support');
            $table->string('whatsapp_cta_label')->default('Ask on WhatsApp');
            $table->string('accent_color', 7)->default('#2dd4bf');
            $table->string('primary_color', 7)->default('#0d9488');
            $table->string('background_color', 7)->default('#090b10');
            $table->string('panel_color', 7)->default('#121722');
            $table->timestamps();
        });

        Schema::create('storefront_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('body')->nullable();
            $table->json('items')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('storefront_navigation_links', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 80);
            $table->string('url', 2048);
            $table->string('location', 20)->default('footer');
            $table->unsignedTinyInteger('column')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('open_new_tab')->default(false);
            $table->timestamps();
            $table->index(['location', 'is_active', 'sort_order'], 'storefront_navigation_display_index');
        });

        Schema::table('banners', function (Blueprint $table): void {
            $table->string('eyebrow')->nullable()->after('title');
            $table->string('alt_text')->nullable()->after('subtitle');
            $table->string('button_label')->nullable()->after('link_url');
            $table->string('layout', 20)->default('image')->after('button_label');
        });

        $now = now();
        DB::table('storefront_settings')->insert([
            'site_name' => 'MAAT Technologies BD',
            'tagline' => 'PRECISION LIGHTING',
            'logo_alt' => 'MAAT Technologies BD logo',
            'default_meta_description' => 'Precision mechanical arm lighting systems by MAAT Technologies BD',
            'support_email' => 'maat.technologies.bd@gmail.com',
            'phone_display' => '01601-934752',
            'whatsapp_number' => '8801601934752',
            'business_hours' => 'Usually within 2 hours between 10:00 and 22:00.',
            'facebook_url' => 'https://www.facebook.com/maattechnologiesbd',
            'instagram_url' => 'https://www.instagram.com/maattechnologiesbd',
            'home_meta_title' => 'Featured Lighting',
            'home_meta_description' => 'Explore featured lighting from MAAT Technologies BD.',
            'hero_badge' => 'Featured lighting',
            'hero_primary_label' => 'Explore the lamp',
            'hero_secondary_label' => 'Shop Lamps',
            'hero_note' => '3D product preview · presentation view',
            'slideshow_enabled' => true,
            'shop_label' => 'Shop Lamps',
            'account_label' => 'Account',
            'sign_in_label' => 'Sign in',
            'wishlist_enabled' => true,
            'cart_enabled' => true,
            'footer_copyright' => '2026 MAAT Technologies BD',
            'footer_status' => 'Storefront online',
            'footer_support_label' => 'Support',
            'whatsapp_cta_label' => 'Ask on WhatsApp',
            'accent_color' => '#2dd4bf',
            'primary_color' => '#0d9488',
            'background_color' => '#090b10',
            'panel_color' => '#121722',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pages = [
            ['about', 'SYSTEM_ORIGIN', 'About Maat Tech BD', 'About', 'About MAAT Technologies BD and our precision mechanical lighting systems in Bangladesh.', [
                'MAAT Technologies BD designs and supplies precision mechanical arm illumination systems for architects, engineers, and creators across Bangladesh.',
                'Every unit is selected for positional accuracy, durability, stable output, and industrial-grade day-to-day performance.',
                'Based in Dhaka and serving customers nationwide through trusted courier and Pathao delivery channels.',
            ], []],
            ['contact', 'COMMS_CHANNEL', 'Contact', 'Contact', 'Contact MAAT Technologies BD for sales, support, WhatsApp orders, and product guidance.', [], []],
            ['faq', 'KNOWLEDGE_BASE', 'FAQ', 'FAQ', 'Frequently asked questions about delivery, payment, warranty, and returns at MAAT Technologies BD.', [], [
                ['title' => 'Delivery time?', 'text' => 'Dhaka: 1 to 3 working days. Outside Dhaka: 2 to 5 working days via Pathao or courier partner.'],
                ['title' => 'Payment methods?', 'text' => 'Cash on Delivery is currently enabled. bKash and Nagad can be added next.'],
                ['title' => 'Warranty?', 'text' => '12 months manufacturing warranty on eligible products unless otherwise stated.'],
                ['title' => 'Return policy?', 'text' => 'Unused items in original packaging can be reviewed for return requests within 7 days.'],
            ]],
            ['shipping', 'DELIVERY_PROTOCOL', 'Shipping Policy', 'Shipping Policy', 'Free delivery policy for MAAT Technologies BD orders throughout Bangladesh.', [
                'Free delivery all across Bangladesh.',
                'Dhaka deliveries typically arrive within 1 to 3 working days. Outside Dhaka deliveries typically arrive within 2 to 5 working days depending on courier coverage.',
                'Orders are processed after order confirmation. The delivery charge at checkout is ৳0 for every supported Bangladesh district.',
                'Customers must provide a valid phone number and delivery address to avoid fulfilment delays.',
            ], []],
            ['returns', 'RETURN_PROTOCOL', 'Return Policy', 'Return Policy', 'Return policy for MAAT Technologies BD products and order handling.', [
                'Return requests are accepted for unused items in original packaging within 7 days of delivery, subject to inspection.',
                'Used, damaged, or altered items may not qualify for return unless the issue is due to manufacturing fault.',
                'Delivery charges may be non-refundable except in confirmed fulfillment or product error cases.',
            ], []],
            ['refund', 'REFUND_RULESET', 'Refund Policy', 'Refund Policy', 'Refund policy for eligible MAAT Technologies BD orders in Bangladesh.', [
                'Refunds are processed after return inspection and approval where the item meets return conditions.',
                'For Cash on Delivery orders, approved refunds are settled through an agreed transfer method after verification.',
                'Processing times may vary depending on courier return completion and banking or mobile financial service delays.',
            ], []],
            ['privacy', 'DATA_POLICY', 'Privacy Policy', 'Privacy Policy', 'Privacy policy for MAAT Technologies BD customer data collection and usage.', [
                'We collect customer information needed to process orders, confirm deliveries, provide support, and protect account security.',
                'Your data is not sold to third parties. Limited information may be shared with payment, courier, and infrastructure providers where operationally necessary.',
                'Customers may request correction of inaccurate profile data by contacting support.',
            ], []],
            ['terms', 'OPERATING_TERMS', 'Terms & Conditions', 'Terms & Conditions', 'Terms and conditions for ordering from MAAT Technologies BD.', [
                'By placing an order, customers agree to provide accurate account, contact, and delivery information.',
                'Prices, availability, and specifications are subject to update without prior notice where required by supply or operational changes.',
                'MAAT Technologies BD reserves the right to cancel suspicious, abusive, or technically invalid orders.',
            ], []],
        ];
        foreach ($pages as [$slug, $eyebrow, $title, $metaTitle, $metaDescription, $body, $items]) {
            DB::table('storefront_pages')->insert([
                'slug' => $slug,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'body' => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'items' => json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $links = [
            ['Products', '/products', 'header', 1, 10, true, false],
            ['About', '/about', 'footer', 1, 10, true, false],
            ['Contact', '/contact', 'footer', 1, 20, true, false],
            ['FAQ', '/faq', 'footer', 1, 30, true, false],
            ['Shipping Policy', '/shipping', 'footer', 2, 10, true, false],
            ['Return Policy', '/returns', 'footer', 2, 20, true, false],
            ['Refund Policy', '/refund', 'footer', 2, 30, true, false],
            ['Privacy Policy', '/privacy', 'footer', 3, 10, true, false],
            ['Terms & Conditions', '/terms', 'footer', 3, 20, true, false],
        ];
        foreach ($links as [$label, $url, $location, $column, $sortOrder, $active, $newTab]) {
            DB::table('storefront_navigation_links')->insert([
                'label' => $label,
                'url' => $url,
                'location' => $location,
                'column' => $column,
                'sort_order' => $sortOrder,
                'is_active' => $active,
                'open_new_tab' => $newTab,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $slides = [
            ['Workspace lighting', null, 'Desk lamp lighting a dual-screen workspace', 'images/storefront/slideshow/01-workspace.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 10],
            ['Focused work', null, 'Desk lamp illuminating a focused work surface', 'images/storefront/slideshow/02-focus.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 20],
            ['Reading light', null, 'Desk lamp positioned over books and reading notes', 'images/storefront/slideshow/03-reading.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 30],
            ['Position anywhere', null, 'Desk lamp shown in reach, repositioned, and folded poses', 'images/storefront/slideshow/04-position.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 40],
            ['Three light modes', null, 'Desk lamp showing warm, neutral, and white light previews', 'images/storefront/slideshow/05-light.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 50],
            ['Space-saving clamp', null, 'Desk-edge clamp preserving the work surface', 'images/storefront/slideshow/06-clamp.webp', '/products/series-x-articulated-lamp', 'View product', 'image', 60],
            ['From first sketch to final review', 'Position the articulated arm where the task needs it, then keep the work surface open.', 'Desk lamp in a lit computer workspace', 'images/storefront/slideshow/07-workspace-portrait.webp', '/products/series-x-articulated-lamp', 'View Product', 'split', 70],
            ['One lamp for changing tasks', 'Move between keyboard work, notes, and close-up tasks without rearranging the desk.', 'Desk lamp illuminating a laptop and monitor workspace', 'images/storefront/slideshow/08-upgraded-portrait.webp', '/products/series-x-articulated-lamp', 'View Product', 'split', 80],
            ['Light where the work moves', 'Swing the head between screens, books, and detailed work while the clamp leaves room below.', 'Swing-arm desk lamp over a computer workstation', 'images/storefront/slideshow/09-built-for-work-portrait.webp', '/products/series-x-articulated-lamp', 'View Product', 'split', 90],
        ];
        foreach ($slides as [$title, $subtitle, $alt, $image, $url, $button, $layout, $sortOrder]) {
            DB::table('banners')->insert([
                'title' => $title,
                'eyebrow' => 'Flexible task lighting',
                'subtitle' => $subtitle,
                'alt_text' => $alt,
                'image_path' => $image,
                'link_url' => $url,
                'button_label' => $button,
                'layout' => $layout,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('banners')->whereIn('image_path', [
            'images/storefront/slideshow/01-workspace.webp',
            'images/storefront/slideshow/02-focus.webp',
            'images/storefront/slideshow/03-reading.webp',
            'images/storefront/slideshow/04-position.webp',
            'images/storefront/slideshow/05-light.webp',
            'images/storefront/slideshow/06-clamp.webp',
            'images/storefront/slideshow/07-workspace-portrait.webp',
            'images/storefront/slideshow/08-upgraded-portrait.webp',
            'images/storefront/slideshow/09-built-for-work-portrait.webp',
        ])->delete();
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['eyebrow', 'alt_text', 'button_label', 'layout']);
        });
        Schema::dropIfExists('storefront_navigation_links');
        Schema::dropIfExists('storefront_pages');
        Schema::dropIfExists('storefront_settings');
    }
};
