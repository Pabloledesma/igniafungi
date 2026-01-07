<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BatchKanban;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchKanbanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_renders_successfully()
    {
        Livewire::test(BatchKanban::class)
            ->assertStatus(200);
    }

    /** @test */
    public function it_opens_the_modal_and_sets_the_selected_batch()
    {
        $batch = Batch::factory()->create();
        $phase = Phase::create(['name' => 'Inoculation', 'slug' => 'inoculation', 'order' => 1]);
        $batch->phases()->attach($phase->id, ['user_id' => 1, 'started_at' => now()]);

        Livewire::test(BatchKanban::class)
            ->call('openTransitionModal', $batch->id)
            ->assertSet('selectedBatchId', $batch->id)
            ->assertSet('showModal', true);
    }

    /** @test */
    public function it_can_confirm_a_transition_and_move_the_batch()
    {
        $user = User::factory()->create();
        $batch = Batch::factory()->create();
        $phase1 = Phase::create(['name' => 'Inoculation', 'slug' => 'inoculation', 'order' => 1]);
        $phase2 = Phase::create(['name' => 'Incubation', 'slug' => 'incubation', 'order' => 2]);
        
        $batch->phases()->attach($phase1->id, ['user_id' => $user->id, 'started_at' => now()]);

        Livewire::actingAs($user)
            ->test(BatchKanban::class)
            ->set('selectedBatchId', $batch->id)
            ->set('nextPhaseId', $phase2->id)
            ->set('notes', 'Micelio vigoroso')
            ->call('confirmTransition')
            ->assertSet('showModal', false)
            ->assertSet('selectedBatchId', null);

        // Verificar que en la base de datos la fase anterior se cerró
        $this->assertDatabaseHas('batch_phases', [
            'batch_id' => $batch->id,
            'phase_id' => $phase1->id,
            'finished_at' => now()->toDateTimeString()
        ]);
    }

    /** @test */
    public function it_can_discard_a_completely_contaminated_batch()
    {
        $user = User::factory()->create();
        $batch = Batch::factory()->create(['quantity' => 50]);
        $phase = Phase::create(['name' => 'Incubation', 'slug' => 'incubation', 'order' => 2]);
        $batch->phases()->attach($phase->id, ['user_id' => $user->id, 'started_at' => now()]);

        Livewire::actingAs($user)
            ->test(BatchKanban::class)
            ->call('discardBatch', $batch->id, 'Trichoderma generalizado', 50)
            ->assertStatus(200);

        // El lote debe estar inactivo
        $this->assertEquals('contaminated', $batch->fresh()->status);
        
        // Debe existir el registro en mermas
        $this->assertDatabaseHas('batch_losses', [
            'batch_id' => $batch->id,
            'quantity' => 50,
            'reason' => 'Trichoderma generalizado'
        ]);
    }
}