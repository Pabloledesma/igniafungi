<?php

namespace Database\Factories;

use App\Models\SupplyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supply>
 */
class SupplyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'supply_category_id' => SupplyCategory::factory(), // We might need a factory for this too, or create one manually
            'quantity' => $this->faker->numberBetween(10, 100),
            'unit' => 'kg',
            'min_stock' => 5,
            'cost_per_unit' => $this->faker->numberBetween(100, 1000),
        ];
    }
}
