<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'grand_total' => $this->faker->randomFloat(2, 10, 500),
            'payment_method' => 'credit_card',
            'payment_status' => 'pending',
            'status' => 'new',
            'currency' => 'COP',
            'shipping_amount' => $this->faker->randomFloat(2, 10, 500),
            'shipping_method' => 'standard',
            'notes' => $this->faker->paragraph(),
            'reference' => $this->faker->word(),
            'bold_transaction_id' => $this->faker->word(),
        ];
    }
}
