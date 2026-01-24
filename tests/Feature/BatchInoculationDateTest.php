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

class BatchInoculationDateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function inoculation_date_is_required_if_strain_is_present()
    {
        // 1. Setup
        $user = User::factory()->create();
        $strain = Strain::factory()->create(['name' => 'Orellana', 'incubation_days' => 15]);
        $recipe = \App\Models\Recipe::factory()->create();

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-DATE-REQ',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id,
            'inoculation_date' => null, // Initially null
        ]);

        // 2. Attempt to save via Form without inoculation_date
        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->fillForm([
                'strain_id' => $strain->id,
                'inoculation_date' => null,
            ])
            ->call('save')
            ->assertHasErrors(['data.inoculation_date']);
    }

    /** @test */
    public function inoculation_date_can_be_saved_if_present()
    {
        // 1. Setup
        $user = User::factory()->create();
        $strain = Strain::factory()->create();
        $recipe = \App\Models\Recipe::factory()->create();

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-DATE-OK',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id,
            'inoculation_date' => null,
        ]);

        // 2. Attempt to save via Form WITH inoculation_date
        // Need to provide phase_id as it is required in the form
        $phase = Phase::factory()->create(['name' => 'Incubación', 'order' => 3]);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => $user->id]);

        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->fillForm([
                'strain_id' => $strain->id,
                'inoculation_date' => '2026-01-01',
                'phase_id' => $phase->id,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $batch->refresh();
        $this->assertEquals('2026-01-01', $batch->inoculation_date->format('Y-m-d'));
    }

    /** @test */
    public function kanban_transition_sets_inoculation_date_if_missing()
    {
        // 1. Setup
        $user = User::factory()->create();
        $strain = Strain::factory()->create();
        $recipe = \App\Models\Recipe::factory()->create();
        $prepPhase = Phase::create(['name' => 'Preparación', 'slug' => 'preparation', 'order' => 1]);
        $inoPhase = Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 2]);

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'KANBAN-DATE',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
            'strain_id' => $strain->id, // Has strain
            'inoculation_date' => null, // No date yet
        ]);

        $batch->phases()->attach($prepPhase->id, ['started_at' => now(), 'user_id' => $user->id]);

        // 2. Transition via Kanban logic (simulating confirming transition)
        // We use the BatchKanban component logic directly or simulate it?
        // Let's use Livewire test for BatchKanban

        Livewire::test(\App\Livewire\BatchKanban::class)
            ->set('selectedBatchId', $batch->id)
            ->set('nextPhaseId', $inoPhase->id)
            ->call('confirmTransition'); // This should trigger the logic we added

        // 3. Verify
        $batch->refresh();
        $this->assertEquals($inoPhase->id, $batch->current_phase->id);
        $this->assertNotNull($batch->inoculation_date, 'Inoculation date should have been set automatically');
        $this->assertEquals(now()->format('Y-m-d'), $batch->inoculation_date->format('Y-m-d'));
    }
}
