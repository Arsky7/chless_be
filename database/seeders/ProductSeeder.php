<?php
// database/seeders/ProductSeeder.php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil semua category IDs
        $categoryIds = \App\Models\Category::pluck('id')->toArray();
        $sizes = ['S', 'M', 'L', 'XL', 'XXL'];

        // Buat 20 produk dummy
        for ($i = 1; $i <= 20; $i++) {
            $product = Product::create([
                'name' => "Product $i",
                'slug' => "product-$i",
                'sku' => "SKU-$i",
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'short_description' => "Short description for product $i",
                'description' => "Full description for product $i",
                'base_price' => rand(50000, 500000),
                'sale_price' => rand(0, 1) ? rand(40000, 450000) : null,
                'is_active' => true,
                'is_featured' => rand(0, 1),
                'track_inventory' => true,
                'is_returnable' => true,
            ]);

            // Buat sizes untuk setiap product
            foreach ($sizes as $size) {
                $stock = rand(0, 50);
                ProductSize::create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'stock' => $stock,
                    'reserved_stock' => 0,
                    'available_stock' => $stock,
                ]);
            }
        }
    }
}