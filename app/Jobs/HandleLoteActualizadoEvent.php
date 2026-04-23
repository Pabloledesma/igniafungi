<?php

namespace App\Jobs;

use App\Models\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class HandleLoteActualizadoEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        $igniaId = $this->payload['Ignia_Id__c'] ?? null;

        if (! $igniaId) {
            Log::warning('LoteActualizado__e sin Ignia_Id__c', $this->payload);

            return;
        }

        $batch = Batch::find((int) $igniaId);

        if (! $batch) {
            Log::warning("LoteActualizado__e: Batch #{$igniaId} no encontrado localmente.");

            return;
        }

        $newStatus = $this->mapEstado($this->payload['Estado_Nuevo__c'] ?? '');

        $batch->updateQuietly([
            'status' => $newStatus,
            'sf_eficiencia_biologica' => $this->payload['Eficiencia_Biologica__c'] ?? null,
            'sf_synced_at' => now(),
        ]);

        Log::info("LoteActualizado__e: Batch #{$igniaId} actualizado a {$newStatus}");
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
