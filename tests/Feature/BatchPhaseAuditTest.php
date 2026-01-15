<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use App\Models\BatchPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Resources\Batches\RelationManagers\PhasesRelationManager;

class BatchPhaseAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function batch_phase_model_relationships_work()
    {
        $user = User::factory()->create();
        $batch = Batch::factory()->create(['user_id' => $user->id]);
        $phase = Phase::factory()->create();

        $batchPhase = BatchPhase::create([
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'notes' => 'Test observation',
        ]);

        $this->assertEquals($batch->id, $batchPhase->batch->id);
        $this->assertEquals($phase->id, $batchPhase->phase->id);
        $this->assertEquals($user->id, $batchPhase->user->id);
    }

    /** @test */
    public function relation_manager_can_list_batch_phases()
    {
        $user = User::factory()->create();
        $batch = Batch::factory()->create(['user_id' => $user->id]);
        $phase = Phase::factory()->create(['name' => 'Testing Phase']);

        $batch->phases()->attach($phase->id, [
            'user_id' => $user->id,
            'started_at' => now(),
            'notes' => 'Audit Log Entry',
        ]);

        Livewire::test(PhasesRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => \App\Filament\Resources\Batches\Pages\EditBatch::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($batch->phases);
        // Note: relation manager lists 'phases', which are Phase models with pivot data
    }
}
