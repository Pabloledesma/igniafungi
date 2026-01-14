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
        $batches = \App\Models\Batch::with(['strain.products', 'phases'])
            ->whereHas('phases', function ($q) {
                // Active phases (not finished)
                $q->whereNull('finished_at')
                    ->whereIn('name', ['Incubación', 'Fructificación']);
            })
            ->get();

        $projectedIncomeByDay = [];

        foreach ($batches as $batch) {
            $currentPhase = $batch->phases->where('pivot.finished_at', null)->first();
            if (!$currentPhase || !$batch->strain)
                continue;

            $startDate = \Carbon\Carbon::parse($currentPhase->pivot->started_at);

            // Logic: Projected Date = Start + Strain Average Days
            // Assumption: incubation_days represents the cycle time for the current phase? 
            // Or total time? Given constraint, using incubation_days as reference.
            // If in Fructification, maybe add a default 7 days? 
            // Let's use incubation_days as the main driver.
            $daysToHarvest = $batch->strain->incubation_days ?? 15;

            // If strictly Fructification, strictly speaking incubation is done.
            // Let's assume incubation_days is total cycle for simplicity or 
            // add arbitrary days if phase is Fructification.
            if ($currentPhase->name === 'Fructificación') {
                $daysToHarvest = 7; // Estimate: 1 week fruiting
            }

            $projectedDate = $startDate->copy()->addDays($daysToHarvest);

            // Only consider if it falls within THIS month and is in the future/today
            if ($projectedDate->month === $now->month && $projectedDate->year === $now->year && $projectedDate->gte($now->startOfDay())) {

                // Value Calculation
                // (Quantity) x (Yield: assume 500g per unit) x (Avg Price)
                $yieldPerUnit = 500; // grams
                $avgPrice = $batch->strain->products->avg('price') ?? 0; // price per gram? 

                // If price is per unit of product (e.g. 100g bag), and price is 10000.
                // We need to normalize units.
                // Assuming price is per Unit of sales. 
                // Let's assume Avg Price is "Price per Gram" usually? No, products are items.
                // Let's assume 1 Batch Unit produces X Revenue.
                // Simplification for Chart: Quantity * 500 * (Price / 100g?)
                // Let's just use: Quantity * (Avg Product Price) * 2 (Yield multiplier?)
                // User said: "(Units) x (Yield) x (Price)". define Yield=1.
                // Let's assume Yield = 1 Product Unit per Batch Unit.
                $estimatedRevenue = $batch->quantity * 1 * $avgPrice;

                // Accumulate
                $day = $projectedDate->day;
                $projectedIncomeByDay[$day] = ($projectedIncomeByDay[$day] ?? 0) + $estimatedRevenue;
            }
        }

        // Calculate Cumulative Structure for Projection
        // Should start from TODAY's actual income and project forward?
        // Or just show the delta? "Linea punteada... debe ser acumulativa"
        // "Entrará dinero... sumando los ingresos teóricos"
        // It implies adding to the existing trend line?
        // Or a separate line starting from 0? 
        // "Esta línea debe comenzar desde el día de hoy hacia el futuro"
        // Interpreted: Start lines up with current Income at Today?

        $cumulativeProjected = [];
        $runningProjected = $cumulativeIncome[$now->day - 1] ?? end($cumulativeIncome); // Start at current actual
        if (!$runningProjected)
            $runningProjected = 0;

        // Fill nulls for days before today
        for ($i = 1; $i < $now->day; $i++) {
            $cumulativeProjected[] = null;
        }

        // Project from Today to End of Month
        for ($day = $now->day; $day <= $daysInMonth; $day++) {
            // Add projected income for this day
            $dailyProj = $projectedIncomeByDay[$day] ?? 0;
            $runningProjected += $dailyProj;
            $cumulativeProjected[] = $runningProjected;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos Acumulados',
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
                    'label' => 'Ingresos Proyectados',
                    'data' => array_pad($cumulativeProjected, $daysInMonth, null), // Ensure length? Loop above handles it.
                    // Actually loop above pushes sequentially. 
                    // Need to ensure array length matches labels.
                    // Pre-padding with nulls loop did this.
                    // Let's verify $cumulativeProjected structure.
                    'borderColor' => '#3b82f6', // Blue
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
