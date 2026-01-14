<?php

namespace Tests\Feature\Financial;

use App\Filament\Widgets\FinancialTrendChart;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\Product;
use App\Models\Strain;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class FinancialProjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calculates_projected_income_correctly()
    {
        // Setup Date: 1st of Month
        $month = now()->month;
        $day1 = now()->setDay(1)->startOfDay();
        Carbon::setTestNow($day1);

        // Setup Models
        $strain = Strain::factory()->create([
            'incubation_days' => 5, // Harvest in 5 days
        ]);

        // Product Price
        Product::factory()->create([
            'strain_id' => $strain->id,
            'price' => 1000, // $1000 per unit
        ]);

        $phase = Phase::factory()->create(['name' => 'Incubación']);

        // Create Batch started today
        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'quantity' => 10,
            // 'current_phase' via pivot
        ]);

        $batch->phases()->attach($phase->id, [
            'started_at' => $day1,
            'finished_at' => null,
            'user_id' => 1,
        ]);

        // Act
        // Logic:
        // Projected Date = Day 1 + 5 = Day 6.
        // Value = 10 (Qty) * 1 (Yield) * 1000 (Price) = 10,000.

        $component = Livewire::test(FinancialTrendChart::class);

        // Inspect Data
        $widget = new FinancialTrendChart();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $projectedData = $data['datasets'][3]['data']; // Index 3 is Total Projection (Index 2 is Pre-sold)

        // Indices are 0-based. Day 6 is index 5.
        // Day 1 to Day 5 should be 0 (or null? code sets null for past, but 0/current for future accum).
        // Since we are at Day 1, "Past" is empty.
        // Trend starts at "Today" (Day 1).
        // Day 1: Projected = 0.
        // Day 6: Projected = 10,000 (Accumulated).

        // Check Day 6 (Index 5)
        $this->assertEquals(10000, $projectedData[5]);

        // Check Day 7 (Index 6) - Should be cumulative (still 10,000 if no other income)
        $this->assertEquals(10000, $projectedData[6]);
    }
}
