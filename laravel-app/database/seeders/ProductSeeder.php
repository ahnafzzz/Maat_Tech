<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryMap = Category::pluck('id', 'slug');

        $products = [
            [
                'category_id' => $categoryMap['articulated-arms'],
                'name' => 'LED Swing-Arm Desk Lamp',
                'slug' => 'series-x-articulated-lamp',
                'description' => 'Bring focused light exactly where your work moves. The articulated arm and adjustable head position easily for reading, study, detailed making, and everyday desk work, while the edge clamp keeps your workspace open.',
                'price' => 3332,
                'discount_amount' => 833,
                'stock' => 24,
                'specs' => [
                    'Light source' => 'LED',
                    'Mounting' => 'Desk-edge clamp',
                    'Adjustment' => 'Articulated swing arm and adjustable lamp head',
                    'Light modes' => 'Warm, neutral, and white',
                    'Brightness' => 'Adjustable',
                    'Power connection' => 'USB',
                    'Controls' => 'In-line light controller',
                ],
                'variants' => [
                    ['key' => 'black', 'label' => 'Black', 'available' => true, 'stock' => 24],
                    ['key' => 'white', 'label' => 'White', 'available' => false, 'stock' => 0],
                ],
                'is_featured' => true,
            ],
            [
                'category_id' => $categoryMap['led-matrix'],
                'name' => 'LED Matrix Panel',
                'slug' => 'led-matrix-panel',
                'description' => 'High-efficiency matrix lighting with low heat output.',
                'price' => 49390,
                'stock' => 18,
                'specs' => ['axes' => 2, 'voltage' => '24V', 'material' => 'Acrylic'],
            ],
            [
                'category_id' => $categoryMap['power-systems'],
                'name' => '24V DC Power Brick',
                'slug' => '24v-dc-power-brick',
                'description' => 'Industrial power brick for stable operation in high-demand setups.',
                'price' => 9790,
                'stock' => 32,
                'specs' => ['axes' => 1, 'voltage' => '24V', 'material' => 'Steel'],
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['slug' => $product['slug']], $product);
        }
    }
}
