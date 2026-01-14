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
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
