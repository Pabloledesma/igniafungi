<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class FinancialTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tendencia Financiera (Acumulada)';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $daysInMonth = $now->daysInMonth;

        // Initialize structure
        $labels = range(1, $daysInMonth);

        // Fetch Month Transactions
        $transactions = \App\Models\Transaction::whereBetween('date', [$startOfMonth, $endOfMonth])->get();

        // Group by Day (1-31)
        $expensesByDay = $transactions->where('type', 'expense')
            ->groupBy(fn($t) => $t->date->day)
            ->map
            ->sum('amount');

        $incomeByDay = $transactions->where('type', 'income')
            ->groupBy(fn($t) => $t->date->day)
            ->map
            ->sum('amount');

        // Calculate Cumulative
        $cumulativeExpenses = [];
        $runningExpense = 0;

        $cumulativeIncome = [];
        $runningIncome = 0;

        foreach ($labels as $day) {
            $runningExpense += ($expensesByDay[$day] ?? 0);
            $cumulativeExpenses[] = $runningExpense;

            $runningIncome += ($incomeByDay[$day] ?? 0);
            $cumulativeIncome[] = $runningIncome;
        }

        // --- Predictive Intelligence: Projected Income from Batches ---
        $batches = \App\Models\Batch::with(['strain.products', 'phases', 'orderItems.order'])
            ->whereHas('phases', function ($q) {
                // Active phases (not finished)
                $q->whereNull('finished_at')
                    ->whereIn('name', ['Incubación', 'Fructificación']);
            })
            ->get();

        $projectedIncomeByDay = [];
        $preSoldIncomeByDay = [];

        foreach ($batches as $batch) {
            $currentPhase = $batch->phases->where('pivot.finished_at', null)->first();
            if (!$currentPhase || !$batch->strain)
                continue;

            $startDate = \Carbon\Carbon::parse($currentPhase->pivot->started_at);

            // Logic: Projected Date = Start + Strain Average Days
            $daysToHarvest = $batch->strain->incubation_days ?? 15;

            if ($currentPhase->name === 'Fructificación') {
                $daysToHarvest = 7;
            }

            $projectedDate = $startDate->copy()->addDays($daysToHarvest);

            // Only consider if it falls within THIS month and is in the future/today
            if ($projectedDate->month === $now->month && $projectedDate->year === $now->year && $projectedDate->gte($now->startOfDay())) {

                $yieldPerUnit = 500; // grams
                $avgPrice = $batch->strain->products->avg('price') ?? 0;

                $estimatedRevenue = $batch->quantity * 1 * $avgPrice;

                // Calculate Pre-sold Revenue (Unpaid)
                $preSoldRevenue = 0;
                if ($batch->orderItems) {
                    foreach ($batch->orderItems as $item) {
                        if ($item->order && $item->order->status !== 'cancelled' && $item->order->payment_status !== 'paid') {
                            $preSoldRevenue += ($item->total_amount ?? 0);
                        }
                    }
                }

                // Remaining Potential Revenue
                $remainingRevenue = max(0, $estimatedRevenue - $preSoldRevenue);

                // Accumulate
                $day = $projectedDate->day;

                $projectedIncomeByDay[$day] = ($projectedIncomeByDay[$day] ?? 0) + $remainingRevenue;
                $preSoldIncomeByDay[$day] = ($preSoldIncomeByDay[$day] ?? 0) + $preSoldRevenue;
            }
        }

        // Calculate Cumulative Structure for Projection
        $cumulativeProjected = []; // Dashed (Remaining)
        $cumulativePreSold = [];   // Solid Blue (Pre-sold)

        $runningProjected = $cumulativeIncome[$now->day - 1] ?? end($cumulativeIncome);
        if (!$runningProjected)
            $runningProjected = 0;

        $runningPreSold = $runningProjected; // Start from Current Real Income baseline
        $runningRem = 0;

        // Fill nulls for days before today
        for ($i = 1; $i < $now->day; $i++) {
            $cumulativeProjected[] = null;
            $cumulativePreSold[] = null;
        }

        // Project from Today to End of Month
        for ($day = $now->day; $day <= $daysInMonth; $day++) {
            // 1. Add Pre-Sold
            $dailyPreSold = $preSoldIncomeByDay[$day] ?? 0;
            $runningPreSold += $dailyPreSold;
            $cumulativePreSold[] = $runningPreSold;

            // 2. Add Remaining to the Pre-Sold baseline (Stacked)
            $dailyRem = $projectedIncomeByDay[$day] ?? 0;
            $runningRem += $dailyRem;
            $runningProjected = $runningPreSold + $runningRem; // Stack on top of Pre-sold
            $cumulativeProjected[] = $runningProjected;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos Reales',
                    'data' => $cumulativeIncome,
                    'borderColor' => '#22c55e', // Green
                    'fill' => false,
                ],
                [
                    'label' => 'Gastos Acumulados',
                    'data' => $cumulativeExpenses,
                    'borderColor' => '#ef4444', // Red
                    'fill' => false,
                ],
                [
                    'label' => 'Ventas Comprometidas',
                    'data' => array_pad($cumulativePreSold, $daysInMonth, null),
                    'borderColor' => '#3b82f6', // Solid Blue
                    'fill' => false,
                ],
                [
                    'label' => 'Proyección Total (Potencial)',
                    'data' => array_pad($cumulativeProjected, $daysInMonth, null),
                    'borderColor' => '#93c5fd', // Light Blue / Dashed
                    'borderDash' => [5, 5],
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
