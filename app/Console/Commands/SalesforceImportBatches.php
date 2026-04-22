<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Strain;
use App\Observers\BatchObserver;
use App\Services\SalesforceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SalesforceImportBatches extends Command
{
    protected $signature = 'salesforce:import-batches {--dry-run : Muestra lo que se importaría sin crear registros}';

    protected $description = 'Importa lotes desde Salesforce que no tienen igniaId asignado';

    public function handle(SalesforceService $salesforce): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('--- MODO DRY RUN: no se crearán registros ---');
        }

        $this->info('Obteniendo lotes desde Salesforce...');

        $lotes = collect($salesforce->getAllLotes())->whereNull('igniaId')->values();

        $this->info("{$lotes->count()} lotes sin igniaId encontrados.");

        if ($lotes->isEmpty()) {
            $this->info('No hay lotes para importar.');

            return self::SUCCESS;
        }

        $strains = Strain::all()->keyBy(fn ($s) => strtolower(trim($s->name)));

        $adminUserId = \App\Models\User::first()->id;

        $bar = $this->output->createProgressBar($lotes->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($lotes as $lote) {
            $sfId = $lote['id'];
            $cepaNombre = $lote['cepa'] ?? null;
            $strain = $cepaNombre
                ? $strains->get(strtolower(trim($cepaNombre)))
                : null;

            $status = $this->mapEstado($lote['estado'] ?? 'active');

            if ($isDryRun) {
                $this->newLine();
                $this->line("  SF: {$sfId} | {$lote['name']} | Cepa: {$cepaNombre} | Estado: {$status}");
                $bar->advance();
                $imported++;

                continue;
            }

            try {
                BatchObserver::clearProcessed();

                $existing = Batch::where('code', $lote['name'])->first();

                if ($existing) {
                    $existing->updateQuietly([
                        'sf_eficiencia_biologica' => $lote['eficienciaBiologica'] ?? null,
                        'sf_total_cosechado_kg' => $lote['totalCosechadoKg'] ?? null,
                        'sf_cantidad_cosechas' => $lote['cantidadCosechas'] ?? null,
                        'sf_archivado' => $lote['archivado'] ?? false,
                        'sf_synced_at' => now(),
                    ]);
                    $batch = $existing;
                } else {
                    $batch = Batch::create([
                        'strain_id' => $strain?->id,
                        'user_id' => $adminUserId,
                        'code' => $lote['name'],
                        'type' => 'bulk',
                        'status' => $status,
                        'initial_wet_weight' => $lote['pesoInicialKg'] ?? 0,
                        'inoculation_date' => $lote['fechaInoculacion'] ?? null,
                        'quantity' => 0,
                        'is_historical' => true,
                        'sf_eficiencia_biologica' => $lote['eficienciaBiologica'] ?? null,
                        'sf_total_cosechado_kg' => $lote['totalCosechadoKg'] ?? null,
                        'sf_cantidad_cosechas' => $lote['cantidadCosechas'] ?? null,
                        'sf_archivado' => $lote['archivado'] ?? false,
                        'sf_synced_at' => now(),
                    ]);
                }

                $salesforce->patchLoteBySfId($sfId, ['igniaId' => $batch->id]);

                $imported++;
            } catch (\Throwable $e) {
                Log::error("Error importando lote SF {$sfId}: ".$e->getMessage());
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$imported} lotes importados, {$skipped} errores.");

        return self::SUCCESS;
    }

    private function mapEstado(string $estado): string
    {
        return match (strtolower(trim($estado))) {
            'activo', 'active', 'en producción', 'en produccion' => 'active',
            'finalizado', 'finalized', 'completed' => 'finalized',
            'contaminado', 'contaminated' => 'contaminated',
            'descartado', 'discarded' => 'discarded',
            default => 'active',
        };
    }
}
