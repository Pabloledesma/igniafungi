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

    protected function setUp(): void
    {
        parent::setUp();
        \App\Observers\BatchObserver::clearProcessed();
    }

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
        // Receta Nanakati params
        $recipe = Recipe::factory()->create([
            'name' => 'Receta Nanakati',
            'dry_weight_ratio' => 0.43
        ]);

        $recipe->supplies()->attach([
            $sawdust->id => ['value' => 24, 'calculation_mode' => 'percentage'],
            $bran->id => ['value' => 19, 'calculation_mode' => 'percentage'],
            $water->id => ['value' => 57, 'calculation_mode' => 'percentage'],
        ]);

        $strain = Strain::factory()->create();

        // 2. Act
        // Batch con 8.6 kg de peso húmedo
        // Ratio: 0.43 -> Peso Seco: 3.698 kg
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id,
            'quantity' => 10,
            'initial_wet_weight' => 8.6,
            'status' => 'active',
            'type' => 'bulk', // Explicitly set type
            'inoculation_date' => now(),
        ]);

        // 3. Assert
        // Nueva Fórmula: (Wet * Ratio) * (Perc / 100)
        // Dry Mass: 3.698
        // Descuento Aserrín: 3.698 * 24% = 0.88752
        // Stock final Aserrín: 100 - 0.88752 = 99.11248

        $sawdust->refresh();
        $this->assertEquals(99.11248, $sawdust->quantity, "El descuento de Aserrín fue incorrecto.");

        // Descuento Salvado: 3.698 * 19% = 0.70262
        // Stock final Salvado: 50 - 0.70262 = 49.29738
        $bran->refresh();
        $this->assertEquals(49.29738, $bran->quantity, "El descuento de Salvado fue incorrecto.");
    }
}
