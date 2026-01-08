<?php

namespace App\Filament\Resources\Harvests\Widgets;

use Filament\Widgets\ChartWidget;

class EficienciaPorCepa extends ChartWidget
{
    protected ?string $heading = 'Eficiencia Por Cepa';

    protected function getData(): array
    {
        // Usamos el modelo Harvest y cargamos la relación a través de Batch
        $harvestsByStrain = \App\Models\Harvest::query()
            ->with(['batch.strain']) // Asegúrate de tener estas relaciones en los modelos
            ->get()
            ->groupBy(function ($harvest) {
                // Agrupamos por el nombre de la cepa, con un fallback por si no tiene
                return $harvest->batch->strain->name ?? 'Sin Cepa';
            })
            ->map(fn ($group) => $group->sum('weight'));

        return [
            'datasets' => [
                [
                    'label' => 'Kilos por Cepa',
                    'data' => $harvestsByStrain->values()->toArray(),
                    'backgroundColor' => [
                        '#8b5cf6', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'
                    ],
                ],
            ],
            'labels' => $harvestsByStrain->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
