<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Strain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
       return [
            // Crea una Cepa nueva automáticamente para este lote
            'strain_id' => null,
            'user_id' => User::factory(),
            'code' => 'LOTE-' . $this->faker->unique()->numberBetween(1000, 9999),
            'weigth_dry' => $this->faker->randomFloat(2, 10, 100),
            'inoculation_date' => now(),
            'quantity' => $this->faker->numberBetween(20, 50),
            'status' => 'preparation'
        ];
    }

    /**
     * Estado: Lote ya inoculado (con Cepas y en Incubación)
     */
    public function inoculated(): static
    {
        return $this->state(fn (array $attributes) => [
            'strain_id' => Strain::factory(),
            'status' => 'incubation',
            'inoculation_date' => now(),
        ]);
    }

    /**
     * Estado: Lote en Fructificación
     */
    public function fruiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'strain_id' => Strain::factory(),
            'status' => 'fruiting',
            'inoculation_date' => now()->subDays(20),
        ]);
    }
}
