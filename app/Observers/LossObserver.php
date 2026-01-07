<?php

namespace App\Observers;

use App\Models\BatchLoss;

class LossObserver
{
    /**
     * Handle the BatchLoss "created" event.
     */
    public function created($loss): void
    {
        $batch = $loss->batch;
        
        if ($batch) {
            // Restamos la cantidad perdida de la cantidad actual del lote
            $newQuantity = max(0, $batch->quantity - $loss->quantity);
            
            $batch->update([
                'quantity' => $newQuantity,
                // Si la pérdida es total, podemos marcar el estado aquí o dejarlo al BatchObserver
            ]);
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
