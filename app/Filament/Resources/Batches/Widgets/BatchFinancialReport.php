<?php

namespace App\Filament\Resources\Batches\Widgets;

use App\Models\Batch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BatchFinancialReport extends BaseWidget
{
    protected function getStats(): array
    {
        // 1. Costo Total de Producción (de todos los lotes activos o en general)
        // Puedes filtrar por usuario o estado si lo prefieres
        $totalCost = Batch::sum('production_cost');

        // 2. Ingreso Estimado
        // Asumiendo un precio de venta promedio de $40.000 COP por kilo seco (o ajusta según tu lógica)
        // O si tienes el precio en el producto vinculado
        // Aquí haremos un cálculo simple: Peso Seco Total * Precio Promedio
        $totalDryWeight = Batch::sum('weigth_dry');
        $averageSalePrice = 40000; // Ejemplo: $40.000 por Kg
        $estimatedRevenue = $totalDryWeight * $averageSalePrice;

        // 3. Beneficio Estimado
        $profit = $estimatedRevenue - $totalCost;

        return [
            Stat::make('Costo Producción Total', '$' . number_format($totalCost, 0))
                ->description('Suma de costos de insumos')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'), // Rojo porque es gasto

            Stat::make('Ingreso Estimado', '$' . number_format($estimatedRevenue, 0))
                ->description('Basado en Peso Seco Total')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Margen Bruto Global', '$' . number_format($profit, 0))
                ->description('Ingreso - Costo')
                ->color($profit > 0 ? 'success' : 'danger'),
        ];
    }
}
