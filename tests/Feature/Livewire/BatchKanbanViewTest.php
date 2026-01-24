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
    /** @test */
    public function inoculation_date_is_displayed_on_batch_card()
    {
        $date = now()->subDays(5);
        $batch = Batch::factory()->create([
            'strain_id' => Strain::factory(),
            'inoculation_date' => $date,
            'status' => 'active',
        ]);

        $batch->phases()->attach($this->preparationPhase->id, [
            'user_id' => $this->user->id,
            'started_at' => now()
        ]);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->assertOk()
            ->assertSee($date->format('d/m/Y'));
    }

    /** @test */
    public function can_select_seed_batch_and_deduct_inventory()
    {
        // 1. Setup
        $strain = \App\Models\Strain::factory()->create();

        // Seed Batch (Grain, Incubating, > 20 days old)
        $seedBatch = \App\Models\Batch::factory()->create([
            'strain_id' => $strain->id, // Same strain
            'type' => 'grain',
            'status' => 'active',
            'quantity' => 10,
            'initial_wet_weight' => 20, // 2kg bags? Let's say big bags.
            'bag_weight' => 2.0,
            'inoculation_date' => now()->subDays(25), // 25 Days old
            'code' => 'SEED-001'
        ]);
        $incubationPhase = \App\Models\Phase::where('slug', 'incubation')->first();
        // Force assign phase
        $seedBatch->phases()->attach($incubationPhase->id, [
            'started_at' => now()->subDays(25),
            'user_id' => $this->user->id
        ]);

        // Target Batch (Substrate, Preparation)
        $targetBatch = \App\Models\Batch::factory()->create([
            'type' => 'bulk',
            'strain_id' => null, // No strain yet
            'initial_wet_weight' => 40, // 40kg target (Max 50)
            'status' => 'active',
            'code' => 'TARGET-001'
        ]);
        $prepPhase = \App\Models\Phase::where('slug', 'preparation')->first();
        $targetBatch->phases()->attach($prepPhase->id, [
            'started_at' => now(),
            'user_id' => $this->user->id
        ]);

        $inoculationPhase = \App\Models\Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 2]);

        // 2. Act
        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            // Open modal for target batch
            ->call('openTransitionModal', $targetBatch->id)
            ->set('nextPhaseId', $inoculationPhase->id)
            ->set('strainId', $strain->id)
            // Expect to see availability (availableInoculumBatches property is populated)
            ->assertSet('availableInoculumBatches', function ($batches) use ($seedBatch) {
                return $batches->contains('id', $seedBatch->id);
            })
            // Select Seed Batch
            ->set('inoculumBatchId', $seedBatch->id)
            ->set('inoculumRatio', 10) // 10%
            ->call('confirmTransition');

        // 3. Assert
        // Target Weight = 100kg. Ratio = 10%. Needed = 10kg.
        // Seed Batch Bag Weight = 2.0kg.
        // Units needed = 10 / 2 = 5 units.
        // Original Seed Quantity = 10. Remaining = 5.

        $this->assertEquals(8, $seedBatch->fresh()->quantity, 'Seed batch quantity should decrease by 2 units');
        $this->assertEquals($strain->id, $targetBatch->fresh()->strain_id, 'Target batch strain should be set');
    }

    /** @test */
    public function cannot_select_seed_batch_with_insufficient_stock()
    {
        // 1. Setup
        $strain = \App\Models\Strain::factory()->create();

        $seedBatch = \App\Models\Batch::factory()->create([
            'strain_id' => $strain->id,
            'type' => 'grain',
            'status' => 'active',
            'quantity' => 3, // Only 3 units available
            'initial_wet_weight' => 6,
            'bag_weight' => 2.0,
            'inoculation_date' => now()->subDays(25),
            'code' => 'SEED-LOW'
        ]);
        $incubationPhase = \App\Models\Phase::where('slug', 'incubation')->first();
        $seedBatch->phases()->attach($incubationPhase->id, [
            'started_at' => now()->subDays(25),
            'user_id' => $this->user->id
        ]);

        $targetBatch = \App\Models\Batch::factory()->create([
            'type' => 'bulk',
            'strain_id' => null,
            'initial_wet_weight' => 40, // 40kg
            'status' => 'active'
        ]);
        $prepPhase = \App\Models\Phase::where('slug', 'preparation')->first();
        $targetBatch->phases()->attach($prepPhase->id, [
            'started_at' => now(),
            'user_id' => $this->user->id
        ]);

        $inoculationPhase = \App\Models\Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 12]);

        // 2. Act
        // target 40kg * 20% = 8kg needed.
        // 8kg / 2kg/unit = 4 units needed.
        // Available: 3.

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->call('openTransitionModal', $targetBatch->id)
            ->set('nextPhaseId', $inoculationPhase->id)
            ->set('strainId', $strain->id)
            ->set('inoculumBatchId', $seedBatch->id)
            ->set('inoculumRatio', 20)
            ->call('confirmTransition')
            ->assertHasErrors(['inoculumBatchId']);

        $this->assertEquals(3, $seedBatch->fresh()->quantity, 'Stock should not change on failure');
    }

    /** @test */
    public function validates_inoculation_ratio_limits()
    {
        $strain = \App\Models\Strain::factory()->create();
        $batch = \App\Models\Batch::factory()->create(['type' => 'bulk', 'status' => 'active']);
        $batch->phases()->attach($this->preparationPhase->id, ['started_at' => now(), 'user_id' => $this->user->id]);
        $inoculationPhase = \App\Models\Phase::create(['name' => 'Inoculación', 'slug' => 'inoculation', 'order' => 12]);

        // Mock seed batch existence to pass basic check
        $seedBatch = \App\Models\Batch::factory()->create([
            'strain_id' => $strain->id,
            'type' => 'grain',
            'status' => 'active',
            'inoculation_date' => now()->subDays(25),
            'quantity' => 100
        ]);
        $incubationPhase = \App\Models\Phase::where('slug', 'incubation')->first();
        $seedBatch->phases()->attach($incubationPhase->id, [
            'started_at' => now()->subDays(25),
            'user_id' => $this->user->id
        ]);

        Livewire::actingAs($this->user)
            ->test(BatchKanban::class)
            ->call('openTransitionModal', $batch->id)
            ->set('nextPhaseId', $inoculationPhase->id)
            ->set('strainId', $strain->id)
            ->set('inoculumBatchId', $seedBatch->id)

            // Too low
            ->set('inoculumRatio', 4)
            ->call('confirmTransition')
            ->assertHasErrors(['inoculumRatio'])

            // Too high
            ->set('inoculumRatio', 21)
            ->call('confirmTransition')
            ->assertHasErrors(['inoculumRatio'])

            // Valid
            ->set('inoculumRatio', 5)
            ->call('confirmTransition')
            ->assertHasNoErrors(['inoculumRatio']);
    }
}
