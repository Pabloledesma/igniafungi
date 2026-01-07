<?php
namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPhaseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_batch_can_advance_to_the_next_phase()
    {
        // 1. Arrange: Preparar datos
        $user = User::factory()->create();
        $batch = Batch::factory()->create();
        $phase1 = Phase::create(['name' => 'Inoculation', 'slug' => 'inoculation']);
        $phase2 = Phase::create(['name' => 'Incubation', 'slug' => 'incubation']);

        // Iniciamos la primera fase
        $batch->phases()->attach($phase1->id, [
            'user_id' => $user->id,
            'started_at' => now()->subDays(2)
        ]);

        // 2. Act: Ejecutar la acción de avanzar
        // Supongamos que creamos un método 'transitionTo' en el modelo Batch
        $batch->transitionTo($phase2, 'Crecimiento de micelio saludable');

        // 3. Assert: Verificar resultados
        // La fase anterior debe estar cerrada (finished_at != null)
        $this->assertDatabaseHas('batch_phases', [
            'batch_id' => $batch->id,
            'phase_id' => $phase1->id,
            'finished_at' => now()->toDateTimeString()
        ]);

        // La nueva fase debe estar abierta
        $this->assertDatabaseHas('batch_phases', [
            'batch_id' => $batch->id,
            'phase_id' => $phase2->id,
            'finished_at' => null,
            'notes' => 'Crecimiento de micelio saludable'
        ]);
    }

    /** @test */
    public function test_a_loss_can_be_recorded_for_a_batch_in_its_current_phase()
    {
        // 1. Sembrar las fases para que el Observer las encuentre
        $this->seed(\Database\Seeders\PhaseSeeder::class);
        // 1. Crear el lote usando la fábrica ajustada
        $batch = Batch::factory()->create(['quantity' => 100]);

        // 2. Registrar la pérdida (Asegúrate de que recordLoss use Eloquent)
        $batch->recordLoss(10, 'Contaminación', 1);

        // 3. REFRESH es vital aquí para recargar la relación y el conteo
        $this->assertEquals(90, $batch->refresh()->quantity);
    }
}