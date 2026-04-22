<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Services\SalesforceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PullBatchFromSalesforce implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $batchId) {}

    public function handle(SalesforceService $salesforce): void
    {
        $batch = Batch::findOrFail($this->batchId);

        $data = $salesforce->getLote($batch->id);

        $batch->updateQuietly([
            'sf_eficiencia_biologica' => $data['eficienciaBiologica'] ?? null,
            'sf_total_cosechado_kg' => $data['totalCosechadoKg'] ?? null,
            'sf_cantidad_cosechas' => $data['cantidadCosechas'] ?? null,
            'sf_archivado' => $data['archivado'] ?? false,
            'sf_synced_at' => now(),
        ]);

        Log::info("Batch {$batch->code} enriquecido desde Salesforce.", [
            'eficiencia' => $data['eficienciaBiologica'] ?? null,
            'total_kg' => $data['totalCosechadoKg'] ?? null,
        ]);
    }
}
