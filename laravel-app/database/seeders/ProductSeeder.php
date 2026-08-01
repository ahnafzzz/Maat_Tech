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
                'name' => 'Series-X Articulated Lamp',
                'slug' => 'series-x-articulated-lamp',
                'description' => 'Premium articulated lighting for precision workspaces.',
                'price' => 27390,
                'stock' => 24,
                'specs' => ['axes' => 4, 'voltage' => '24V', 'material' => 'CNC Aluminum'],
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
