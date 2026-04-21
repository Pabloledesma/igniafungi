<?php

namespace App\Jobs;

use App\Models\Harvest;
use App\Services\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncHarvestToSalesforce implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $harvestId) {}

    public function handle(SalesforceService $salesforce): void
    {
        $harvest = Harvest::findOrFail($this->harvestId);

        $salesforce->upsertHarvest([
            'id' => $harvest->id,
            'weight' => $harvest->weight,
            'harvest_date' => $harvest->harvest_date?->toDateString(),
            'notes' => $harvest->notes,
            'batch_id' => $harvest->batch_id,
        ]);
    }
}
