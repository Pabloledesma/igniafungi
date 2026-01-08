<?php

namespace App\Filament\Resources\BatchLosses\Widgets;

use Filament\Widgets\ChartWidget;

class LossChart extends ChartWidget
{
    protected ?string $heading = 'Loss Chart';

    protected function getData(): array
    {
        // Obtenemos las pérdidas agrupadas por motivo
        $data = \App\Models\BatchLoss::query()
            ->selectRaw('reason, sum(quantity) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason');

        return [
            'datasets' => [
                [
                    'label' => 'Bolsas Perdidas',
                    'data' => $data->values()->toArray(),
                    // Colores semánticos: Rojo para contaminación, Naranja para manejo, etc.
                    'backgroundColor' => [
                        '#ef4444', // Rojo (Contaminación)
                        '#f59e0b', // Ámbar (Manejo)
                        '#3b82f6', // Azul (Esterilización)
                        '#6366f1', // Índigo (Otros)
                    ],
                ],
            ],
            'labels' => $data->keys()->map(fn($key) => ucfirst($key))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // Cambia 'line' por 'doughnut'
    }

}
