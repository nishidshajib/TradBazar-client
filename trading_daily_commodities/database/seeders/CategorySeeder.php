<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and gadgets'],
            ['name' => 'Clothing', 'description' => 'Apparel and fashion items'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and gardening supplies'],
            ['name' => 'Books', 'description' => 'Books and educational materials'],
            ['name' => 'Sports', 'description' => 'Sports equipment and accessories'],
            ['name' => 'Food & Beverages', 'description' => 'Food items and drinks'],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
