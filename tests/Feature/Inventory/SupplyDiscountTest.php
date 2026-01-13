<?php

namespace Tests\Feature\Inventory;

use App\Models\Batch;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\User;
use App\Models\Strain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyDiscountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_correctly_calculates_hydrated_weight_based_on_dry_weight_for_deductions()
    {
        // 1. Arrange
        $user = User::factory()->create();

        // Insumos
        $sawdust = Supply::factory()->create(['name' => 'Aserrín', 'quantity' => 100, 'unit' => 'kg']);
        $bran = Supply::factory()->create(['name' => 'Salvado', 'quantity' => 50, 'unit' => 'kg']);
        $water = Supply::factory()->create(['name' => 'Agua', 'quantity' => 1000, 'unit' => 'litros']);

        // Receta Nanakati (Simulada)
        // Secos: 43% (Aserrín 24% + Salvado 19%)
        // Agua: 57%
        $recipe = Recipe::factory()->create(['name' => 'Receta Nanakati']);

        $recipe->supplies()->attach([
            $sawdust->id => ['value' => 24, 'calculation_mode' => 'percentage'],
            $bran->id => ['value' => 19, 'calculation_mode' => 'percentage'],
            $water->id => ['value' => 57, 'calculation_mode' => 'percentage'],
        ]);

        $strain = Strain::factory()->create();

        // 2. Act
        // Batch con 3.44 kg de peso seco
        // Total esperado = 3.44 / 0.43 = 8 kg de sustrato húmedo total
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id,
            'quantity' => 10,
            'weigth_dry' => 3.44, // Input clave
            'status' => 'incubating',
            'inoculation_date' => now(),
        ]);

        // 3. Assert
        // Descuento Aserrín esperado: 8 kg * 24% = 1.92 kg
        // Stock final Aserrín: 100 - 1.92 = 98.08

        $sawdust->refresh();
        $this->assertEquals(98.08, $sawdust->quantity, "El descuento de Aserrín fue incorrecto. Se esperaba 98.08, se encontró {$sawdust->quantity}");

        // Verificación opcional de Salvado (8 * 19% = 1.52) -> 50 - 1.52 = 48.48
        $bran->refresh();
        $this->assertEquals(48.48, $bran->quantity, "El descuento de Salvado fue incorrecto.");
    }
}
