<?php

namespace Database\Factories;

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
            'strain_id' => Strain::factory(),
            
            // Genera códigos únicos tipo "LOTE-2023-AB"
            'code' => $this->faker->unique()->bothify('LOTE-####-??'),
            
            // Peso seco aleatorio entre 10kg y 100kg
            'substrate_weight_dry' => $this->faker->randomFloat(2, 10, 100),
            
            // Fecha aleatoria de este año
            'inoculation_date' => $this->faker->dateTimeThisYear(),
            
            // Cantidad de bolsas (ej. 20 a 50)
            'quantity' => $this->faker->numberBetween(20, 50),
        ];
    }
}
