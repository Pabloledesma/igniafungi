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
}
