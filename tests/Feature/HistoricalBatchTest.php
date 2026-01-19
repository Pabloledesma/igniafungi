<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\RecipeSupply;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HistoricalBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear observer state to ensure clean tests
        \App\Observers\BatchObserver::clearProcessed();
    }

    /** @test */
    public function scope_active_filters_only_active_batches()
    {
        // Arrange
        Batch::factory()->create(['status' => 'active', 'code' => 'ACTIVE-001']);
        Batch::factory()->create(['status' => 'seeded', 'code' => 'HIST-001']);
        Batch::factory()->create(['status' => 'finalized', 'code' => 'HIST-002']);

        // Act
        $activeBatches = Batch::active()->get();

        // Assert
        $this->assertCount(1, $activeBatches);
        $this->assertEquals('ACTIVE-001', $activeBatches->first()->code);
    }

    /** @test */
    public function historical_batch_can_be_created_without_phase_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // We create a batch directly with null phase_id (property) and non-active status
        $batch = Batch::create([
            'user_id' => $user->id,
            'type' => 'bulk',
            'quantity' => 10,
            'weigth_dry' => 5,
            'status' => 'completed',
            'bag_weight' => 2.5
            // 'phase_id' => null // Not in fillable, ignored by create anyway
        ]);

        // Simulating the virtual property assignment if needed, but create() returns model.
        // The important thing is that it SAVES with status completed

        $this->assertDatabaseHas('batches', [
            'id' => $batch->id,
            'status' => 'completed',
        ]);

        // Ensure no rows in batch_phases pivot for this batch
        $this->assertCount(0, $batch->phases);
    }

    /** @test */
    public function historical_batch_does_not_deduct_inventory()
    {
        // 1. Arrange: Supplies and Recipe
        $user = User::factory()->create();
        $this->actingAs($user);

        $supply = Supply::create([
            'name' => 'Worm Castings',
            'quantity' => 100,
            'unit' => 'kg',
            'category' => 'substrate'
        ]);

        $recipe = Recipe::create(['name' => 'Legacy Mix']);

        RecipeSupply::create([
            'recipe_id' => $recipe->id,
            'supply_id' => $supply->id,
            'calculation_mode' => 'fixed_per_unit',
            'value' => 1, // 1kg per batch unit
        ]);

        // 2. Act: Create Historical Batch (Status != active)
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'type' => 'bulk',
            'quantity' => 10, // Should deduct 10kg if it was active
            'weigth_dry' => 10,
            'status' => 'seeded', // HISTORICAL
            'phase_id' => null,
            'bag_weight' => 1
        ]);

        // 3. Assert: Inventory should UNCHANGED (100)
        $supply->refresh();
        $this->assertEquals(100, $supply->quantity, 'Inventory should not change for historical batches');
    }

    /** @test */
    public function normal_active_batch_still_deducts_inventory()
    {
        // 1. Arrange: Supplies and Recipe
        $user = User::factory()->create();
        $this->actingAs($user);

        $incubation = Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubation', 'order' => 1]);

        $supply = Supply::create([
            'name' => 'Fresh Sawdust',
            'quantity' => 100,
            'unit' => 'kg',
            'category' => 'substrate'
        ]);

        $recipe = Recipe::create(['name' => 'Standard Mix']);

        RecipeSupply::create([
            'recipe_id' => $recipe->id,
            'supply_id' => $supply->id,
            'calculation_mode' => 'fixed_per_unit',
            'value' => 1,
        ]);

        // 2. Act: Create ACTIVE Batch
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'type' => 'bulk',
            'quantity' => 10, // Should deduct 10kg
            'weigth_dry' => 10,
            'status' => 'active',
            'phase_id' => $incubation->id,
            'bag_weight' => 1
        ]);

        // 3. Assert: Inventory should be 90
        $supply->refresh();
        $this->assertEquals(90, $supply->quantity, 'Inventory for active batch should be deducted');
    }
}
