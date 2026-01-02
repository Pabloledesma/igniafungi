<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'category_id' => \App\Models\Category::factory(),
            'strain_id' => \App\Models\Strain::factory(),
            'description' => $this->faker->paragraph(),
            'short_description' => $this->faker->paragraph(),
            'slug' => str($this->faker->sentence(3))->slug(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'is_active' => true,
        ];
    }
}
