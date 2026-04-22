<?php

namespace App\Console\Commands;

use App\Jobs\PullBatchFromSalesforce;
use App\Models\Batch;
use App\Services\SalesforceService;
use Illuminate\Console\Command;

class SalesforcePullBatches extends Command
{
    protected $signature = 'salesforce:pull-batches {--id= : ID de un lote específico}';

    protected $description = 'Trae datos computados de Salesforce y enriquece los lotes locales';

    public function handle(SalesforceService $salesforce): int
    {
        if ($id = $this->option('id')) {
            $this->pullSingle((int) $id);

            return self::SUCCESS;
        }

        $this->info('Obteniendo todos los lotes desde Salesforce...');

        $lotes = $salesforce->getAllLotes();

        if (empty($lotes)) {
            $this->warn('No se encontraron lotes en Salesforce.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($lotes));
        $bar->start();

        $synced = 0;

        foreach ($lotes as $lote) {
            $igniaId = $lote['igniaId'] ?? null;

            if (! $igniaId) {
                $bar->advance();

                continue;
            }

            $batch = Batch::find($igniaId);

            if (! $batch) {
                $bar->advance();

                continue;
            }

            $batch->updateQuietly([
                'sf_eficiencia_biologica' => $lote['eficienciaBiologica'] ?? null,
                'sf_total_cosechado_kg' => $lote['totalCosechadoKg'] ?? null,
                'sf_cantidad_cosechas' => $lote['cantidadCosechas'] ?? null,
                'sf_archivado' => $lote['archivado'] ?? false,
                'sf_synced_at' => now(),
            ]);

            $synced++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$synced} lotes sincronizados correctamente.");

        return self::SUCCESS;
    }

    private function pullSingle(int $id): void
    {
        $batch = Batch::findOrFail($id);
        $this->info("Sincronizando lote #{$id} ({$batch->code})...");

        PullBatchFromSalesforce::dispatch($id);

        $this->info('Job despachado a la queue.');
    }
}
