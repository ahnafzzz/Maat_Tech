<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Articulated Arms', 'slug' => 'articulated-arms', 'description' => 'Precision lighting arms for workstations and studios.'],
            ['name' => 'LED Matrix', 'slug' => 'led-matrix', 'description' => 'High output lighting panels for demanding environments.'],
            ['name' => 'Power Systems', 'slug' => 'power-systems', 'description' => 'Reliable power and control systems for industrial setups.'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
