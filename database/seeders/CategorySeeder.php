<?php
// database/seeders/CategorySeeder.php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'T-Shirt',
                'slug' => 't-shirt',
                'description' => 'Various t-shirts for men and women',
                'is_active' => true
            ],
            [
                'name' => 'Long Sleeves',
                'slug' => 'long-sleeves',
                'description' => 'Long sleeve shirts and tops',
                'is_active' => true
            ],
            [
                'name' => 'Outwear',
                'slug' => 'outwear',
                'description' => 'Jackets, hoodies, and outerwear',
                'is_active' => true
            ],
            [
                'name' => 'Pants',
                'slug' => 'pants',
                'description' => 'Trousers, jeans, and bottoms',
                'is_active' => true
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => 'Bags, hats, and other accessories',
                'is_active' => true
            ],
            [
                'name' => 'Tanktop/Croptop',
                'slug' => 'tanktop-croptop',
                'description' => 'Tank tops and crop tops',
                'is_active' => true
            ],
            [
                'name' => 'Hat',
                'slug' => 'hat',
                'description' => 'Caps, beanies, and hats',
                'is_active' => true
            ],
            [
                'name' => 'Bag',
                'slug' => 'bag',
                'description' => 'Tote bags, backpacks, and more',
                'is_active' => true
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}