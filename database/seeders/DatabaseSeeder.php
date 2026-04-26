<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create 100 additional users for load testing
        User::factory(100)->create();

        // Create 10 categories
        $categories = Category::factory(10)->create();

        // Create 200 products distributed across categories
        Product::factory(200)->create([
            'category_id' => fn() => $categories->random()->id,
        ]);
    }
}
