<?php

namespace Tests\Feature\Financial;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\Strain;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Observers\BatchObserver::clearProcessed();
    }

    /** @test */
    public function it_calculates_and_stores_production_cost_on_batch_creation()
    {
        // 1. Arrange
        $user = User::factory()->create();

        // Insumos con Costo
        // Costo del Aserrín: $1000/kg
        $sawdust = Supply::factory()->create(['name' => 'Aserrín', 'cost_per_unit' => 1000, 'unit' => 'kg']);
        // Costo del Agua: $50/litro (marginal)
        $water = Supply::factory()->create(['name' => 'Agua', 'cost_per_unit' => 50, 'unit' => 'litros']);
        // Costo de Bolsa: $200/unidad
        $bag = Supply::factory()->create(['name' => 'Bolsa', 'cost_per_unit' => 200, 'unit' => 'units']);

        $recipe = Recipe::factory()->create(['name' => 'Receta Costeada']);

        // Definir la Receta
        // Aserrín 40% (del peso total)
        // Agua 60% (del peso total)
        // Bolsa 1 por unidad
        $recipe->supplies()->attach([
            $sawdust->id => ['value' => 40, 'calculation_mode' => 'percentage'],
            $water->id => ['value' => 60, 'calculation_mode' => 'percentage'],
            $bag->id => ['value' => 1, 'calculation_mode' => 'fixed_per_unit'],
        ]);

        $strain = Strain::factory()->create();

        // 2. Act
        // Crear Lote
        // 4kg de Peso SECO. 
        // Según receta (40% seco), el Peso Total Hidratado será 4 / 0.40 = 10kg.
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id,
            'quantity' => 10, // 10 unidades (bolsas)
            'initial_wet_weight' => 10, // 10kg húmedo * 0.4 ratio = 4kg seco
            'status' => 'active',
            'type' => 'bulk', // Explicitly set type
            'inoculation_date' => now(),
        ]);

        // 3. Assert
        // Cálculo Esperado (Nueva Lógica con Ratio):
        // Peso Húmedo = 10 kg
        // Ratio = 0.4 (Default)
        // Peso Seco Base = 4 kg

        // Consumo Aserrín: 4kg (Seco) * 40% = 1.6kg. Costo: 1.6 * 1000 = $1600
        // Consumo Agua: 4kg (Seco) * 60% = 2.4kg. Costo: 2.4 * 50 = $120
        // Consumo Bolsas: 1 bolsa/unidad * 10 unidades = 10 bolsas. Costo: 10 * 200 = $2000

        // Costo Total = 1600 + 120 + 2000 = 3720

        $this->assertEquals(3720, $batch->production_cost, "El costo de producción calculado ({$batch->production_cost}) no coincide con el esperado (3720).");
    }
}
