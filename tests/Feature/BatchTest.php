<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Recipe;
use App\Models\Strain;
use App\Models\Supply;
use Livewire\Livewire;
use App\Models\Harvest;
use App\Models\RecipeSupply;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Resources\Batches\Pages\ListBatches;

class BatchTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function a_batch_belongs_to_a_strain()
    {
        // 1. Arrange: Creamos una cepa y un lote
        $strain = Strain::factory()->create();
        $batch = Batch::factory()->create(['strain_id' => $strain->id]);

        // 2. Act & Assert: Verificamos que el lote sepa quién es su "padre"
        $this->assertInstanceOf(Strain::class, $batch->strain);
    }

    /** @test */
    public function it_calculates_biological_efficiency_correctly()
    {
        // 1. Arrange: 
        // Lote con 2kg de sustrato seco (ignoramos la humedad para el cálculo final)
        $batch = Batch::factory()->create([
            'weigth_dry' => 2.00 
        ]);

        // Simulamos 2 cosechas: 
        // Cosecha 1: 1.5kg
        // Cosecha 2: 0.5kg
        // Total: 2.0kg de hongos frescos
        Harvest::factory()->create(['batch_id' => $batch->id, 'weight' => 1.5]);
        Harvest::factory()->create(['batch_id' => $batch->id, 'weight' => 0.5]);

        // 2. Act: Pedimos la eficiencia biológica
        // Fórmula: (Total Hongos / Sustrato Seco) * 100
        // (2.0 / 2.0) * 100 = 100%
        $efficiency = $batch->biological_efficiency;

        // 3. Assert
        $this->assertEquals(100, $efficiency);
    }

      /** @test */
    public function can_download_qr_label_pdf()
    {
        // 1. Arrange (Preparar)
        // Necesitamos un usuario para entrar al panel
        $user = User::factory()->create();
         // 2. Act & Assert (Actuar y Verificar)
        // Simulamos ser el usuario y entrar al componente "ListBatches"
        $this->actingAs($user);
        $strain = Strain::factory()->create(['name' => 'Pleurotus']);
        // Creamos el lote sin pasarle código
        $batch = Batch::create([
            'strain_id' => $strain->id,
            'weigth_dry' => 10,
            'quantity' => 50,
            'inoculation_date' => now(),
        ]);

        Livewire::test(ListBatches::class)
            ->callTableAction('pdf', $batch) // Buscamos la acción 'pdf' en la fila de $batch
            ->assertFileDownloaded("{$batch->code}.pdf"); // Verificamos que descargue el archivo correcto
    }

    /** @test */
    public function it_generates_code_automatically_on_model_creation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $strain = Strain::factory()->create(['name' => 'Pleurotus']);
        
        // Creamos el lote sin pasarle código
        $batch = Batch::create([
            'strain_id' => $strain->id,
            'weigth_dry' => 10,
            'quantity' => 50,
            'inoculation_date' => now(),
        ]);

        // Verificamos que el código siga el patrón PLE-AAMMDD-XX
        $this->assertNotNull($batch->code);
        $this->assertStringContainsString('PLE-', $batch->code);
        $this->assertEquals(13, strlen($batch->code)); // PLE-260103-12 tiene 11 caracteres
    }

    /** @test */
    public function it_automatically_finalizes_any_batch_when_quantity_is_zero()
    {
        // Escenario: Un lote de sustrato (bulk) que se agota por contaminación
        $batch = Batch::factory()->create([
            'type' => 'bulk',
            'quantity' => 2,
            'status' => 'incubation'
        ]);

        // Act: Bajamos la cantidad a 0 (simulando cualquier operación)
        $batch->update(['quantity' => 0]);

        // Assert: El modelo debió cambiar su propio estado
        $this->assertEquals('finalized', $batch->status);
        $this->assertStringContainsString('finalizado automáticamente', $batch->observations);
    }

    /** @test */
    public function it_decrements_supply_stock_correctly_on_batch_creation()
    {
        // 1. Arrange: Crear la infraestructura necesaria
        $user = User::factory()->create();
        $this->actingAs($user);

        // Crear Insumos con stock inicial
        $aserrin = Supply::create([
            'name' => 'Aserrín',
            'quantity' => 100, // kg
            'unit' => 'kg',
            'category' => 'substrate'
        ]);

        $bolsas = Supply::create([
            'name' => 'Bolsas 2kg',
            'quantity' => 100, // unidades
            'unit' => 'units',
            'category' => 'packaging'
        ]);

        // Crear la Receta
        $recipe = Recipe::create(['name' => 'Fórmula Maestra']);

        // Vincular insumos a la receta usando el modelo pivot RecipeSupply
        // Aserrín al 10%
        RecipeSupply::create([
            'recipe_id' => $recipe->id,
            'supply_id' => $aserrin->id,
            'calculation_mode' => 'percentage',
            'value' => 10,
        ]);

        // 1 Bolsa por unidad de lote
        RecipeSupply::create([
            'recipe_id' => $recipe->id,
            'supply_id' => $bolsas->id,
            'calculation_mode' => 'fixed_per_unit',
            'value' => 1,
        ]);

        // 2. Act: Crear un Batch (esto dispara el BatchObserver)
        // Lote de 20 unidades con 40kg de peso seco total
        $batch = Batch::create([
            'strain_id' => Strain::factory()->create()->id,
            'type' => 'bulk',
            'recipe_id' => $recipe->id,
            'quantity' => 20,
            'weigth_dry' => 40,
            'inoculation_date' => now(),
        ]);

        // 3. Assert: Verificar que la matemática sea exacta
        $aserrin->refresh();
        $bolsas->refresh();

        // Cálculo Aserrín: 100 - (40 * 0.10) = 96
        $this->assertEquals(96, $aserrin->quantity);

        // Cálculo Bolsas: 100 - (20 * 1) = 80
        $this->assertEquals(80, $bolsas->quantity);
    }

}
