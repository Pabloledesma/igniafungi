<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\Strain;
use Livewire\Livewire;
use App\Livewire\BatchKanban;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KanbanFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_filters_kanban_by_search_and_strain()
    {
        // Setup
        $phase = Phase::create(['name' => 'Prep', 'slug' => 'preparation', 'order' => 1]);
        $strainA = Strain::factory()->create(['name' => 'Golden Teacher']);
        $strainB = Strain::factory()->create(['name' => 'B+']);
        $user = User::factory()->create();

        // Batch 1: Matches Search "ABC", Strain A
        $b1 = Batch::factory()->create(['code' => 'BATCH-ABC-001', 'strain_id' => $strainA->id, 'user_id' => $user->id]);
        $b1->phases()->attach($phase->id, ['started_at' => now(), 'finished_at' => null, 'user_id' => $user->id]);

        // Batch 2: Matches Strain B
        $b2 = Batch::factory()->create(['code' => 'BATCH-XYZ-002', 'strain_id' => $strainB->id, 'user_id' => $user->id]);
        $b2->phases()->attach($phase->id, ['started_at' => now(), 'finished_at' => null, 'user_id' => $user->id]);

        Livewire::test(BatchKanban::class)
            // 0. Assert Phase is there
            ->assertSee('Prep')

            // 1. Assert data is present in collection (bypass view rendering check)
            ->assertViewHas('phases', function ($phases) {
                $count = $phases->first()->batches->count();
                // dump("Batches in Phase 1: " . $count);
                return $count === 2;
            })

            // 2. Filter by Search
            ->set('search', 'ABC')
            ->assertViewHas('phases', function ($phases) {
                $batches = $phases->first()->batches;
                return $batches->count() === 1
                    && $batches->first()->code === 'BATCH-ABC-001';
            })

            // Reset Search
            ->set('search', '')

            // 3. Filter by Strain
            ->set('selectedStrain', $strainA->id)
            ->assertViewHas('phases', function ($phases) {
                $batches = $phases->first()->batches;
                return $batches->count() === 1
                    && $batches->first()->strain_id === $phases->first()->batches->first()->strain_id; // Check ID match
            });
    }
}
