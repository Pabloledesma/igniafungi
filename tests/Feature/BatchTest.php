<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Strain;
use Livewire\Livewire;
use App\Models\Harvest;
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
            'substrate_weight_dry' => 2.00 
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
        
        // Necesitamos un lote para imprimir
        $batch = Batch::factory()->create([
            'code' => '2025-PRUEBA-QR'
        ]);

        // 2. Act & Assert (Actuar y Verificar)
        // Simulamos ser el usuario y entrar al componente "ListBatches"
        $this->actingAs($user);

        Livewire::test(ListBatches::class)
            ->callTableAction('pdf', $batch) // Buscamos la acción 'pdf' en la fila de $batch
            ->assertFileDownloaded("Lote-{$batch->code}.pdf"); // Verificamos que descargue el archivo correcto
    }
}
