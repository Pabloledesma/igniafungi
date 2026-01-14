<?php

namespace App\Observers;

use App\Models\Harvest;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class HarvestObserver
{
    /**
     * Handle the Harvest "created" event.
     */
    public function created(Harvest $harvest): void
    {
        $batch = $harvest->batch;

        if ($batch && $batch->strain_id) {
            // Buscamos productos de la misma cepa
            // EXCLUYENDO aquellos que pertenecen a la categoría 'Deshidratados'
            $products = Product::where('strain_id', $batch->strain_id)
                ->whereDoesntHave('category', function ($query) {
                    $query->where('name', 'like', '%Deshidratado%');
                })->get();

            $products->each(function ($product) use ($harvest) {
                $product->increment('stock', $harvest->weight);
            });
        }
    }

    /**
     * Handle the Harvest "updated" event.
     */
    public function updated(Harvest $harvest): void
    {
        //
    }

    /**
     * Handle the Harvest "deleted" event.
     */
    public function deleted(Harvest $harvest): void
    {
        //
    }

    /**
     * Handle the Harvest "restored" event.
     */
    public function restored(Harvest $harvest): void
    {
        //
    }

    /**
     * Handle the Harvest "force deleted" event.
     */
    public function forceDeleted(Harvest $harvest): void
    {
        //
    }
}
