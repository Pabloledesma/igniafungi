<?php

namespace App\Observers;

use App\Models\BatchLoss;
use Illuminate\Support\Facades\Log;

class LossObserver
{
    /**
     * Handle the BatchLoss "created" event.
     */
    public function created(BatchLoss $loss): void
    {
        // Usamos el ID directamente para evitar recargas de relaciones nulas
        $batch = \App\Models\Batch::find($loss->batch_id);
        
        if ($batch) {
            // decrement() es una consulta SQL directa, es más limpia para inventarios
            $batch->decrement('quantity', $loss->quantity);
        }
    }

    /**
     * Handle the BatchLoss "updated" event.
     */
    public function updated(BatchLoss $batchLoss): void
    {
        //
    }

    /**
     * Handle the BatchLoss "deleted" event.
     */
    public function deleted(BatchLoss $batchLoss): void
    {
        //
    }

    /**
     * Handle the BatchLoss "restored" event.
     */
    public function restored(BatchLoss $batchLoss): void
    {
        //
    }

    /**
     * Handle the BatchLoss "force deleted" event.
     */
    public function forceDeleted(BatchLoss $batchLoss): void
    {
        //
    }
}
