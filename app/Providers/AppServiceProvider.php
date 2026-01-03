<?php

namespace App\Providers;

use App\Models\Batch;
use App\Observers\BatchObserver;
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
        Batch::observe(BatchObserver::class);
    }
}
