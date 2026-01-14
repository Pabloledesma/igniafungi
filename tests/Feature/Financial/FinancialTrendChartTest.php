<?php

namespace Tests\Feature\Financial;

use App\Filament\Widgets\FinancialTrendChart;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class FinancialTrendChartTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_calculates_cumulative_trends_correctly()
    {
        // Setup dates (Days 1, 5, 10 of current month)
        $month = now()->month;
        $year = now()->year;

        $day1 = now()->setDay(1)->startOfDay();
        $day5 = now()->setDay(5)->startOfDay();
        $day10 = now()->setDay(10)->startOfDay();

        // Transaction 1: Expense on Day 1 ($1000)
        Transaction::create([
            'description' => 'Rent',
            'amount' => 1000,
            'type' => 'expense',
            'category' => 'services',
            'date' => $day1,
        ]);

        // Transaction 2: Expense on Day 5 ($200)
        Transaction::create([
            'description' => 'Supplies',
            'amount' => 200,
            'type' => 'expense',
            'category' => 'supplies',
            'date' => $day5,
        ]);

        // Transaction 3: Income on Day 5 ($1500)
        Transaction::create([
            'description' => 'Sale',
            'amount' => 1500,
            'type' => 'income',
            'category' => 'sales',
            'date' => $day5,
        ]);

        // Act
        $widget = new FinancialTrendChart();
        // Access protected getData via reflection or Livewire test
        // Easier to inspect via Livewire

        // Assert
        // Logic:
        // Day 1: Exp=1000, Inc=0
        // Day 5: Exp=1000+200=1200, Inc=0+1500=1500
        // Day 10: Exp=1200, Inc=1500

        $component = Livewire::test(FinancialTrendChart::class);

        // We can inspect the VIEW data, but ChartWidget usually exposes 'datasets' and 'labels'

        $datasets = $component->get('datasets');
        $labels = $component->get('labels'); // Is this property exposed? ChartWidget uses configuration.
        // Actually, ChartWidget puts data into the view, but testing it directly can be tricky.
        // Let's rely on calling the protected getData method via reflection for precise logic verification.

        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $cumulativeExpenses = $data['datasets'][1]['data'];
        $cumulativeIncome = $data['datasets'][0]['data'];

        // Index 0 is Day 1
        $this->assertEquals(1000, $cumulativeExpenses[0]); // Day 1
        $this->assertEquals(0, $cumulativeIncome[0]); // Day 1

        // Index 4 is Day 5
        $this->assertEquals(1200, $cumulativeExpenses[4]); // Day 5 (1000 + 200)
        $this->assertEquals(1500, $cumulativeIncome[4]); // Day 5

        // Index 9 is Day 10
        $this->assertEquals(1200, $cumulativeExpenses[9]); // Day 10 (Still 1200)
        $this->assertEquals(1500, $cumulativeIncome[9]); // Day 10
    }
}
