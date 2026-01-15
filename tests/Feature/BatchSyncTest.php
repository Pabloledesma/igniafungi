<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use App\Models\Recipe;
use App\Filament\Resources\Batches\Pages\EditBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BatchSyncTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function batch_edit_form_loads_correct_phase()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create();
        $phase = Phase::create(['name' => 'Incubación', 'slug' => 'incubation', 'order' => 2]);
        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-001',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'weigth_dry' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
        ]);

        // 2. Assign Phase (Manually via relationship to ensure DB state)
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => $user->id]);

        // 3. Verify DB State before test
        $this->assertEquals($phase->id, $batch->current_phase->id, 'The batch should be in the assigned phase in the DB.');

        // 4. Test Edit Form Load
        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->assertFormSet(['phase_id' => $phase->id]);
    }

    /** @test */
    public function saving_batch_phase_updates_kanban_state()
    {
        // 1. Setup Data
        $user = User::factory()->create();
        $recipe = Recipe::factory()->create();
        $phase1 = Phase::create(['name' => 'Incubación', 'slug' => 'incubation', 'order' => 2]);
        $phase2 = Phase::create(['name' => 'Fructificación', 'slug' => 'fruiting', 'order' => 3]);

        $batch = Batch::create([
            'user_id' => $user->id,
            'code' => 'TEST-002',
            'type' => 'bulk',
            'status' => 'active',
            'quantity' => 10,
            'weigth_dry' => 40,
            'bag_weight' => 2,
            'recipe_id' => $recipe->id,
        ]);

        $batch->phases()->attach($phase1->id, ['started_at' => now(), 'user_id' => $user->id]);

        // 2. Test Changing Phase in Form
        Livewire::test(EditBatch::class, ['record' => $batch->id])
            ->fillForm(['phase_id' => $phase2->id])
            ->call('save')
            ->assertHasNoErrors();

        // 3. Verify DB State Updated (Transition Logic)
        $batch->refresh();
        $this->assertEquals($phase2->id, $batch->current_phase->id, 'Batch should have moved to the new phase.');

        // Verify old phase is closed
        $oldPhasePivot = $batch->phases()->where('phase_id', $phase1->id)->first()->pivot;
        $this->assertNotNull($oldPhasePivot->finished_at, 'Previous phase should be closed.');
    }
}
