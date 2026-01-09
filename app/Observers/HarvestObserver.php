<?php

namespace App\Observers;

use App\Models\Harvest;

class HarvestObserver 
{
    /**
     * Handle the Harvest "created" event.
     */
    public function created(Harvest $harvest): void
    {
        if ($harvest->weight >= 5) {
            // Lógica para notificar a los suscriptores: 
            // "¡Nueva cosecha de {$harvest->batch->strain->name} disponible!"
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
