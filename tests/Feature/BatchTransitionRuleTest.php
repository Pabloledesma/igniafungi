<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use App\Models\Strain;
use App\Filament\Resources\Batches\Pages\EditBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BatchTransitionRuleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cannot_transition_to_inoculation_without_strain()
    {
        // 1. Setup
        $user = User::factory()->create();
        $recipe = \App\Models\Recipe::factory()->create();
        $preparationPhase = Phase::create(['name' => 'Preparación', 'slug' => 'preparation', 'order' => 1]);
        $inoculationPhase = Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 2]);

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-NO-STRAIN',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
            'strain_id' => null, // Explicitly null
        ]);

        $batch->phases()->attach($preparationPhase->id, ['started_at' => now(), 'user_id' => $user->id]);

        // 2. Attempt to change phase to Inoculation via Form
        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->fillForm(['phase_id' => $inoculationPhase->id])
            ->call('save')
            ->assertHasErrors(['data.phase_id']); // Should fail validation

        // 3. Verify DB state did not change
        $batch->refresh();
        $this->assertEquals($preparationPhase->id, $batch->current_phase->id, 'Batch should remain in preparation phase');
    }

    /** @test */
    public function can_transition_to_inoculation_with_strain()
    {
        // 1. Setup
        $user = User::factory()->create();
        $strain = Strain::factory()->create();
        $recipe = \App\Models\Recipe::factory()->create();
        $preparationPhase = Phase::create(['name' => 'Preparación', 'slug' => 'preparation', 'order' => 1]);
        $inoculationPhase = Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 2]);

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-WITH-STRAIN',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id, // Has strain
        ]);

        $batch->phases()->attach($preparationPhase->id, ['started_at' => now(), 'user_id' => $user->id]);

        // 2. Attempt to change phase to Inoculation via Form
        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->fillForm([
                'phase_id' => $inoculationPhase->id,
                'inoculation_date' => now(), // Required now
            ])
            ->call('save')
            ->assertHasNoErrors();

        // 3. Verify DB state changed
        $batch->refresh();
        $this->assertEquals($inoculationPhase->id, $batch->current_phase->id, 'Batch should have moved to Inoculation');
    }
}
