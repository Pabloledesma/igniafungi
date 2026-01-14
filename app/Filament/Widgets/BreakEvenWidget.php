<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class BreakEvenWidget extends Widget
{
    protected string $view = 'filament.widgets.break-even-widget';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $now = now();

        $expenses = \App\Models\Transaction::where('type', 'expense')
            ->whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->sum('amount');

        $income = \App\Models\Transaction::where('type', 'income')
            ->whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->sum('amount');

        $percentage = $expenses > 0 ? ($income / $expenses) * 100 : ($income > 0 ? 100 : 0);
        $missing = max(0, $expenses - $income);

        return [
            'expenses' => $expenses,
            'income' => $income,
            'percentage' => round($percentage),
            'missing' => $missing,
        ];
    }
}
