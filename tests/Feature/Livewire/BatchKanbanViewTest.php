<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BatchKanban;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\User;
use App\Models\Strain;
use App\Models\Recipe;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchKanbanViewTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $incubationPhase;
    protected $preparationPhase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $this->preparationPhase = Phase::create(['name' => 'Preparación', 'slug' => 'preparation', 'order' => 1]);
        $this->incubationPhase = Phase::create(['name' => 'Incubación', 'slug' => 'incubation', 'order' => 2]);

        $strain = Strain::factory()->create(['name' => 'Test Strain']);
        $recipe = Recipe::factory()->create();
    }

    /** @test */
    public function advance_button_is_hidden_for_grain_batch_in_incubation()
    {
        // Grain batch in incubation
        $batch = Batch::factory()->create([
            'type' => 'grain',
            'status' => 'active',
        ]);

        $batch->phases()->attach($this->incubationPhase->id, [
            'user_id' => $this->user->id,
            'started_at' => now()
        ]);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->assertOk()
            ->assertDontSeeHtml('wire:click="openTransitionModal(' . $batch->id . ')"')
            ->assertDontSee('Avanzar →');
    }

    /** @test */
    public function advance_button_is_visible_for_bulk_batch_in_incubation()
    {
        // Bulk batch in incubation
        $batch = Batch::factory()->create([
            'type' => 'bulk',
            'status' => 'active',
        ]);

        $batch->phases()->attach($this->incubationPhase->id, [
            'user_id' => $this->user->id,
            'started_at' => now()
        ]);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->assertOk()
            ->assertSeeHtml('wire:click="openTransitionModal(' . $batch->id . ')"')
            ->assertSee('Avanzar →');
    }

    /** @test */
    public function advance_button_is_visible_for_grain_batch_in_preparation()
    {
        // Grain batch in preparation
        $batch = Batch::factory()->create([
            'type' => 'grain',
            'status' => 'active',
        ]);

        $batch->phases()->attach($this->preparationPhase->id, [
            'user_id' => $this->user->id,
            'started_at' => now()
        ]);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->assertOk()
            ->assertSeeHtml('wire:click="openTransitionModal(' . $batch->id . ')"')
            ->assertSee('Avanzar →');
    }
}
