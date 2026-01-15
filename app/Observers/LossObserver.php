<?php

namespace App\Observers;

use App\Models\BatchLoss;
use Illuminate\Support\Facades\Log;

class LossObserver
{
    /**
     * Handle the BatchLoss "created" event.
     */
    public static $shouldUpdateBatch = true;

    public function created(BatchLoss $loss): void
    {
        $this->recalculateBatchContamination($loss);
    }

    public function updated(BatchLoss $loss): void
    {
        $this->recalculateBatchContamination($loss);
    }

    public function deleted(BatchLoss $loss): void
    {
        $this->recalculateBatchContamination($loss);
    }

    protected function recalculateBatchContamination(BatchLoss $loss): void
    {
        if (!self::$shouldUpdateBatch) {
            return;
        }

        $batch = \App\Models\Batch::find($loss->batch_id);

        if ($batch) {
            // Prevent infinite loops
            \App\Observers\BatchObserver::$isSyncingLoss = true;

            // Recalculate total contamination from DB
            $totalContaminated = $batch->losses()->where('reason', 'Contaminación')->sum('quantity');
            $batch->contaminated_quantity = $totalContaminated;

            // Note: We are NOT automatically adjusting 'quantity' here because 'quantity'
            // represents *live* stock. If a loss is created, the stock is decremented ONCE.
            // If we blindly reset 'quantity' based on losses, we might double-count or mess up 
            // if 'quantity' was adjusted by other means (sales, harvests).
            // However, implementing strictly what was requested: "automates calculation of contaminated_units".
            // The previous logic decremented quantity on CREATE. We should probably keep that behavior 
            // specifically for CREATION (loss of stock), but recalculation of the *report* field 
            // shouldn't necessarily change stock history unless we rebuild it completely.

            // For now, we only sync the 'contaminated_quantity' reporting field as requested.
            // The actual stock decrement logic was likely handled in previous 'created' or needs to be preserved?
            // User request #4 in previous turn said: "si se incrementan unidades contaminadas se debe crear batch loss".
            // Now we are doing reverse: BatchLoss -> updates Contaminated Field.

            // Important: If we are creating a loss, we MUST decrement the live quantity.
            // But 'updated'/'deleted' might imply restoring stock? simpler to stick to reporting field for now.
            // But wait, look at previous code: "$batch->quantity -= $loss->quantity;"
            // We should preserve that for 'created'.

            $batch->save();

            \App\Observers\BatchObserver::$isSyncingLoss = false;
        }

        // Re-apply stock decrement only on Created (as original logic intended)
        if ($loss->wasRecentlyCreated && $loss->reason === 'Contaminación') {
            $batch->decrement('quantity', $loss->quantity);
        }
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
