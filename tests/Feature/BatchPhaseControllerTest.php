<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPhaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos un usuario para las peticiones autenticadas
        $this->user = User::factory()->create();
    }

    /** @test */
    public function an_authenticated_user_can_transition_a_batch_to_a_new_phase()
    {
        // Arrange
        $batch = Batch::factory()->create();
        $phase1 = Phase::create(['name' => 'Inoculation', 'slug' => 'inoculation']);
        $phase2 = Phase::create(['name' => 'Incubation', 'slug' => 'incubation']);

        // Iniciamos la fase 1
        $batch->phases()->attach($phase1->id, ['user_id' => $this->user->id, 'started_at' => now()]);

        // Act
        $response = $this->actingAs($this->user)
            ->post(route('batches.transition', $batch), [
                'phase_id' => $phase2->id,
                'notes' => 'Transición de prueba exitosa'
            ]);

        // Assert
        $response->assertStatus(302); // Redirección tras éxito (si usas back())
        $this->assertDatabaseHas('batch_phases', [
            'batch_id' => $batch->id,
            'phase_id' => $phase2->id,
            'notes' => 'Transición de prueba exitosa',
            'finished_at' => null
        ]);

        // Verificar que la anterior se cerró
        $this->assertDatabaseMissing('batch_phases', [
            'batch_id' => $batch->id,
            'phase_id' => $phase1->id,
            'finished_at' => null
        ]);
    }

    /** @test */
    public function transition_requires_a_valid_phase_id()
    {
        $batch = Batch::factory()->create();

        $response = $this->actingAs($this->user)
            ->post(route('batches.transition', $batch), [
                'phase_id' => 999, // ID inexistente
            ]);

        $response->assertSessionHasErrors('phase_id');
    }
}