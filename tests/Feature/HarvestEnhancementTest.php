<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\User;
use App\Models\Harvest;
use App\Models\Strain;
use App\Filament\Resources\Harvests\Pages\CreateHarvest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HarvestEnhancementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creates_user_id_automatically_on_harvest_creation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $batch = Batch::factory()->create(['user_id' => $user->id, 'type' => 'bulk', 'status' => 'active']);

        Livewire::test(CreateHarvest::class)
            ->fillForm([
                'batch_id' => $batch->id,
                'weight' => 2.5,
                'harvest_date' => now()->format('Y-m-d'),
                // user_id is hidden and should default to auth id
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('harvests', [
            'batch_id' => $batch->id,
            'weight' => 2.5,
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function validation_fails_if_weight_exceeds_5kg()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $batch = Batch::factory()->create(['user_id' => $user->id, 'type' => 'bulk', 'status' => 'active']);

        Livewire::test(CreateHarvest::class)
            ->fillForm([
                'batch_id' => $batch->id,
                'weight' => 5.1,
                'harvest_date' => now()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasErrors(['data.weight']);
        // The error message validation might be specific, but standard assertHasErrors works
    }
}
