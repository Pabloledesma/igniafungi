<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Services\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBatchToSalesforce implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $batchId) {}

    public function handle(SalesforceService $salesforce): void
    {
        $batch = Batch::with('strain')->findOrFail($this->batchId);

        $salesforce->upsertBatch([
            'id' => $batch->id,
            'code' => $batch->code,
            'strain' => $batch->strain?->name,
            'status' => $batch->status,
            'type' => $batch->type,
            'inoculation_date' => $batch->inoculation_date?->toDateString(),
            'quantity' => $batch->quantity,
            'initial_wet_weight' => $batch->initial_wet_weight,
        ]);

        PullBatchFromSalesforce::dispatch($this->batchId)->delay(now()->addSeconds(5));
    }
}
