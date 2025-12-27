<?php

namespace App\Filament\Resources\Orders\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use Illuminate\Support\Number;


class OrderStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;
    
    protected function getStats(): array
    {
        return [
            Stat::make('New Order', Order::query()->where('status', 'new')->count()),
            Stat::make('Processing Order', Order::query()->where('status', 'processing')->count()),
            Stat::make('Shipped Order', Order::query()->where('status', 'shipped')->count()),
            Stat::make('Average Price', Number::currency(Order::query()->avg('grand_total'), 'COP')),
        ];
    }
}
