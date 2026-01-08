<?php

namespace App\Filament\Resources\BatchLosses\Widgets;

use App\Models\BatchLoss;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LossOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Bolsas Perdidas (Mes)', 
                BatchLoss::whereMonth('created_at', now()->month)->sum('quantity'))
                ->description('Impacto en el inventario')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Causa Principal', 
                BatchLoss::groupBy('reason')
                    ->selectRaw('reason, sum(quantity) as total')
                    ->orderByDesc('total')->first()?->reason ?? 'N/A')
                ->description('Motivo más frecuente'),

            Stat::make('Tasa de Pérdida', 
                number_format((BatchLoss::sum('quantity') / \App\Models\Batch::sum('quantity')) * 100, 2) . '%')
                ->label('Eficiencia Global')
                ->color('warning'),
        ];
    }
}
