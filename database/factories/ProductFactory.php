<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $brands = ['Rolex', 'Gucci', 'Louis Vuitton', 'Chanel', 'Dior', 'Prada', 'Versace', 'Armani', 'Cartier', 'Tiffany'];

        return [
            'category_id' => Category::factory(),
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'price'       => $this->faker->randomFloat(2, 50, 10000),
            'stock'       => $this->faker->numberBetween(0, 200),
            'brand'       => $this->faker->randomElement($brands),
            'image'       => 'placeholder.jpg',
            'is_active'   => true,
        ];
    }
}
