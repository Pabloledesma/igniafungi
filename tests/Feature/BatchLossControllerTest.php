<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchLossControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function an_authenticated_user_can_record_a_loss_for_a_batch()
    {
        $batch = Batch::factory()->create(['quantity' => 100]);
        $phase = Phase::create(['name' => 'Incubation', 'slug' => 'incubation']);
        
        // Simular que el lote está en incubación
        $batch->phases()->attach($phase->id, [
            'user_id' => $this->user->id, 
            'started_at' => now()
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('batches.losses.store', $batch), [
                'quantity' => 15,
                'reason' => 'Contaminación (Trichoderma)',
                'details' => 'Se detectó mancha verde en el sector A.'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('batch_losses', [
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
            'quantity' => 15,
            'reason' => 'Contaminación (Trichoderma)'
        ]);
    }
}