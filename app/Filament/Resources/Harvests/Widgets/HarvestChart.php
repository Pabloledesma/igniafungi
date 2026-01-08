<?php

namespace App\Filament\Resources\Harvests\Widgets;

use Filament\Widgets\ChartWidget;

class HarvestChart extends ChartWidget
{
    protected ?string $heading = 'Harvest Chart';

   protected function getData(): array
    {
        // Agrupamos la suma de kilos por día en los últimos 30 días
        $data = \App\Models\Harvest::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(harvest_date) as date, sum(weight) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Kilos Cosechados',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => '#10b981', // Verde éxito
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
