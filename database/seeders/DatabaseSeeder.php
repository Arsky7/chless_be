<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Panggil semua seeder
        $this->call([
            UserSeeder::class,       // Buat users dulu
            CategorySeeder::class,    // Baru categories
            ProductSeeder::class,     // Nanti buat ini
        ]);
        
        // Atau bisa juga jalankan factory langsung
        // User::factory(10)->create();
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}