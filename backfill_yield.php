<?php
use App\Models\Batch;

echo "Backfilling Data...\n";
$count = 0;
// Update ALL active batches with no yield or 0 yield
Batch::where(function ($q) {
    $q->whereNull('expected_yield')->orWhere('expected_yield', 0);
})->chunk(100, function ($batches) use (&$count) {
    foreach ($batches as $batch) {
        if ($batch->quantity > 0) {
            $batch->expected_yield = $batch->quantity * 500; // 500g per unit default
            $batch->save();
            $count++;
            echo "Updated Batch {$batch->code}\n";
        }
    }
});

echo "Updated $count batches.\n";
