<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Phase;
use Livewire\Livewire;
use App\Livewire\BatchKanban;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KanbanModalTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_opens_transition_modal_from_cooling_to_inoculation_and_assigns_strain()
    {
        // 1. Setup phases
        $pPrep = Phase::create(['name' => 'Prep', 'slug' => 'preparation', 'order' => 1]);
        $pCool = Phase::create(['name' => 'Cooling', 'slug' => 'cooling', 'order' => 2]);
        $pInoc = Phase::create(['name' => 'Inoculation', 'slug' => 'inoculation', 'order' => 3]);

        $strain = \App\Models\Strain::factory()->create();

        // 2. Create Batch in Cooling (without strain)
        $user = User::factory()->create();
        $batch = Batch::factory()->create([
            'user_id' => $user->id,
            'strain_id' => null, // No genetics yet
            'status' => 'active'
        ]);

        // Attach phase manually
        $batch->phases()->attach($pCool->id, ['started_at' => now(), 'finished_at' => null, 'user_id' => $user->id]);

        // 3. Test Component
        Livewire::test(BatchKanban::class)
            // Open Modal
            ->call('openTransitionModal', $batch->id)
            ->assertSet('showModal', true)
            ->assertSet('nextPhaseId', $pInoc->id)
            // Verify strains are loaded
            ->assertSee($strain->name)

            // Try to confirm without strain (should fail)
            ->call('confirmTransition')
            ->assertHasErrors(['strainId'])

            // Select Strain and Confirm
            ->set('strainId', $strain->id)
            ->call('confirmTransition')
            ->assertHasNoErrors();

        // 4. Verify Database
        $this->assertEquals($strain->id, $batch->fresh()->strain_id);
    }
}
