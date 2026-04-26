<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Watches', 'Jewelry', 'Perfumes', 'Bags', 'Sunglasses',
            'Accessories', 'Electronics', 'Clothing', 'Shoes', 'Gifts',
        ]);

        return [
            'name'        => $name,
            'slug'        => Str::slug($name),
            'description' => $this->faker->sentence(),
        ];
    }
}
