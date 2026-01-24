<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\Recipe;
use App\Models\Supply;
use App\Models\RecipeSupply;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchRefinedCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Observers\BatchObserver::clearProcessed();
    }

    /** @test */
    public function it_calculates_ingredient_deduction_using_wet_weight_and_dry_ratio()
    {
        // 1. Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create Supply (Carve)
        $carve = Supply::create([
            'name' => 'Carve',
            'quantity' => 100, // Initial stock
            'unit' => 'kg',
            'category' => 'substrate'
        ]);

        // Create Recipe with dry_weight_ratio = 0.40
        $recipe = Recipe::create([
            'name' => 'Receta Validacion',
            'type' => 'substrate',
            'dry_weight_ratio' => 0.40
        ]);

        // Add Carve 1% (percentage mode)
        RecipeSupply::create([
            'recipe_id' => $recipe->id,
            'supply_id' => $carve->id,
            'calculation_mode' => 'percentage',
            'value' => 1, // 1%
        ]);

        // 2. Act: Create Batch with 16kg Wet Weight
        $batch = Batch::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'initial_wet_weight' => 16.0, // Wet input
            'quantity' => 1,
            'status' => 'active',
            'type' => 'bulk',
        ]);

        // 3. Assert
        $carve->refresh();

        // Expected Deduction:
        // Wet = 16
        // Ratio = 0.40
        // Dry Mass = 16 * 0.40 = 6.4 kg
        // Deduction = 6.4 * 1% = 0.064 kg
        // Remaining = 100 - 0.064 = 99.936

        $this->assertEquals(99.936, $carve->quantity);
    }
    /** @test */
    public function it_deducts_specific_spawn_on_delayed_inoculation()
    {
        // 1. Setup Data
        $strain = \App\Models\Strain::factory()->create(['name' => 'Melena']);

        // Supply 1: Generic Spawn (Placeholder in Recipe)
        $genericSpawn = \App\Models\Supply::factory()->create([
            'name' => 'Semilla Generica',
            'quantity' => 1000
        ]);

        // Supply 2: Specific Spawn (The one to be deducted)
        $specificSpawn = \App\Models\Supply::factory()->create([
            'name' => 'Semilla Melena',
            'quantity' => 100
        ]);

        $recipe = \App\Models\Recipe::factory()->create(['dry_weight_ratio' => 0.40]);

        // Recipe requires 10% of Dry Weight as "Semilla Generica"
        $recipe->supplies()->attach($genericSpawn->id, [
            'value' => 10,
            'calculation_mode' => 'percentage'
        ]);

        // 2. Create Batch WITHOUT Strain (Preparation Phase)
        // Batch: 10kg Wet -> 4kg Dry. 10% of 4kg = 0.4kg Spawn needed.
        $batch = \App\Models\Batch::factory()->create([
            'recipe_id' => $recipe->id,
            'strain_id' => null,
            'initial_wet_weight' => 10,
            'quantity' => 5, // 5 bags
            'status' => 'active',
            'type' => 'bulk'
        ]);

        // Assert: NO deduction from specific spawn yet (Lazy Deduction)
        $this->assertEquals(100, $specificSpawn->fresh()->quantity, 'Specific spawn should not be deducted yet');
        // Assert: NO deduction from generic spawn (Lazy Deduction rule)
        $this->assertEquals(1000, $genericSpawn->fresh()->quantity, 'Generic generic spawn should be skipped if strain is null');

        // 3. Assign Strain (Mock Inoculation)
        $batch->update(['strain_id' => $strain->id]);

        // 4. Assert Deduction
        // Expected Deduction: 10kg * 0.4 (Ratio) * 0.10 (10%) = 0.4 kg
        $this->assertEquals(99.6, $specificSpawn->fresh()->quantity, 'Specific spawn should be deducted after strain assignment');
    }
}
