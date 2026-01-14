<?php

namespace Tests\Feature\Financial;

use App\Filament\Widgets\FinancialTrendChart;
use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Phase;
use App\Models\Product;
use App\Models\Strain;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;

class GapCloserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_splits_projection_into_pre_sold_and_remaining()
    {
        // Setup Date: 1st of Month
        $month = now()->month;
        $day1 = now()->setDay(1)->startOfDay();
        Carbon::setTestNow($day1);

        $user = User::factory()->create();

        // 1. Setup Batch (Harvest in 5 days, estimated value 10,000)
        $strain = Strain::factory()->create(['incubation_days' => 5]);
        $product = Product::factory()->create(['strain_id' => $strain->id, 'price' => 1000]);
        $phase = Phase::factory()->create(['name' => 'Incubación']);

        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'quantity' => 10, // Yield = 10 * 1 * 1000 = 10,000 total potential
        ]);
        $batch->phases()->attach($phase->id, [
            'started_at' => $day1,
            'user_id' => $user->id
        ]);

        // 2. Create Pre-sale Order (Unpaid) for HALF the batch (5,000)
        $order = Order::create([
            'user_id' => $user->id,
            'grand_total' => 5000,
            'status' => 'new',
            'payment_status' => 'pending',
            'currency' => 'usd',
            'shipping_amount' => 0,
            'payment_method' => 'stripe'
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 5,
            'unit_amount' => 1000,
            'total_amount' => 5000,
        ]);

        // Act
        $widget = new FinancialTrendChart();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $preSoldData = $data['datasets'][2]['data']; // Solid Blue
        $totalProjData = $data['datasets'][3]['data']; // Dashed Blue/Light

        // Assert Day 6 (Index 5)
        // Real Income (Green) = 0
        // Pre-Sold (Solid Blue) = 5,000
        // Total Projected (Dashed) = 5,000 (Pre-Sold) + 5,000 (Remaining) = 10,000.

        $this->assertEquals(5000, $preSoldData[5], 'Pre-sold should be 5000');
        $this->assertEquals(10000, $totalProjData[5], 'Total Projected should be 10000');

        // 3. Pay the Order -> Should move to Real Income (Green)
        // OrderObserver should create Transaction.
        $order->update(['payment_status' => 'paid']);

        // Refresh Widget Logic
        $data2 = $method->invoke($widget);

        $realIncomeData2 = $data2['datasets'][0]['data'];
        $preSoldData2 = $data2['datasets'][2]['data'];
        $totalProjData2 = $data2['datasets'][3]['data'];

        // Assert Day 1 (Transaction Date = Now = Day 1)
        // Real Income = 5000.
        // Pre-sold = 0 (since it is paid).
        // Total Projected = 5000 + 0 + Remaining (5000) = 10,000.
        // Wait, Projected Date is Day 6. Transaction is Day 1.
        // Logic:
        // Day 1: Real Income 5000.
        // Day 6: 
        // - Pre-sold logic: searches unpaid orders. Found none. Pre-sold = 0.
        // - Remaining Logic: Total (10,000) - Pre-sold (0)? No.
        // - Remaining logic calculation: max(0, Estimated(10,000) - PreSoldUnpaid(0) ) = 10,000? 
        // - ISSUE: The "Remaining" logic as written calculates "Pre-sold" only based on Unpaid. 
        //   It doesn't subtract "Paid items linked to batch" from "Estimated Yield".
        //   So Remaining would show 10,000.
        //   And Real Income is 5,000.
        //   So Total Stacked = Real(5000) + PreSold(0) + Remaining(10000) = 15,000.
        //   This is WRONG. Total potential is 10,000.

        // FIX NEEDED in FinancialTrendChart: "Remaining" must subtract ALL sold items (Paid or Unpaid) from Estimated.
        // But currently I verified the failure first.
    }
}
