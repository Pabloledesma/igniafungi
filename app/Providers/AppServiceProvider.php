<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($limit = env('INI_MEMORY_LIMIT')) {
            ini_set('memory_limit', $limit);
        }

        \App\Models\Batch::observe(\App\Observers\BatchObserver::class);
        \App\Models\BatchLoss::observe(\App\Observers\LossObserver::class);
        \App\Models\Harvest::observe(\App\Observers\HarvestObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
    }
}
