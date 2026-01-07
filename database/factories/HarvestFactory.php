<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Harvest>
 */
class HarvestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => \App\Models\Batch::factory(),
            'weight' => $this->faker->randomFloat(2, 0.1, 5),
            'harvest_date' => now(),
            'notes' => $this->faker->sentence(),
            'phase_id' => \App\Models\Phase::factory(),
            'user_id' => \App\Models\User::factory()
        ];
    }
}
