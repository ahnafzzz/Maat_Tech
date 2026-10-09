<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $slides = [
            ['Position anywhere', null, 'Desk lamp shown in reach, repositioned, and folded poses', 'images/storefront/slideshow/04-position.webp', 'image', 40],
            ['Three light modes', null, 'Desk lamp showing warm, neutral, and white light previews', 'images/storefront/slideshow/05-light.webp', 'image', 50],
            ['Space-saving clamp', null, 'Desk-edge clamp preserving the work surface', 'images/storefront/slideshow/06-clamp.webp', 'image', 60],
            ['One lamp for changing tasks', 'Move between keyboard work, notes, and close-up tasks without rearranging the desk.', 'Desk lamp illuminating a laptop and monitor workspace', 'images/storefront/slideshow/08-upgraded-portrait.webp', 'split', 80],
            ['Light where the work moves', 'Swing the head between screens, books, and detailed work while the clamp leaves room below.', 'Swing-arm desk lamp over a computer workstation', 'images/storefront/slideshow/09-built-for-work-portrait.webp', 'split', 90],
        ];

        DB::table('banners')->where('image_path', 'images/storefront/slideshow/07-workspace-portrait.webp')->update([
            'link_url' => '/products/series-x-articulated-lamp',
            'sort_order' => 70,
            'updated_at' => $now,
        ]);
        DB::table('banners')->whereIn('image_path', [
            'images/storefront/slideshow/01-workspace.webp',
            'images/storefront/slideshow/02-focus.webp',
            'images/storefront/slideshow/03-reading.webp',
        ])->update(['link_url' => '/products/series-x-articulated-lamp', 'updated_at' => $now]);

        foreach ($slides as [$title, $subtitle, $alt, $image, $layout, $sortOrder]) {
            if (DB::table('banners')->where('image_path', $image)->exists()) {
                continue;
            }
            DB::table('banners')->insert([
                'title' => $title,
                'eyebrow' => 'Flexible task lighting',
                'subtitle' => $subtitle,
                'alt_text' => $alt,
                'image_path' => $image,
                'link_url' => '/products/series-x-articulated-lamp',
                'button_label' => $layout === 'split' ? 'View Product' : 'View product',
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
            'images/storefront/slideshow/04-position.webp',
            'images/storefront/slideshow/05-light.webp',
            'images/storefront/slideshow/06-clamp.webp',
            'images/storefront/slideshow/08-upgraded-portrait.webp',
            'images/storefront/slideshow/09-built-for-work-portrait.webp',
        ])->delete();
    }
};
