<?php

namespace App\Console\Commands;

use App\Jobs\HandleLoteActualizadoEvent;
use App\Services\SalesforceCometDService;
use Illuminate\Console\Command;

class SalesforceListenEvents extends Command
{
    protected $signature = 'salesforce:listen {--replay=-1 : Replay ID (-1=nuevos, -2=todos los retenidos)}';

    protected $description = 'Escucha Platform Events de Salesforce via CometD (long-polling)';

    public function handle(SalesforceCometDService $cometd): int
    {
        $channel = '/event/LoteActualizado__e';
        $replayId = (int) $this->option('replay');

        $this->info('Conectando a Salesforce CometD...');
        $cometd->handshake();
        $this->info('Handshake exitoso.');

        $cometd->subscribe($channel, $replayId);
        $this->info("Suscrito a {$channel} (replayId: {$replayId})");
        $this->line('Escuchando eventos... (Ctrl+C para detener)');

        $cometd->listen($channel, function (array $event) {
            $payload = $event['data']['payload'] ?? [];
            $igniaId = $payload['Ignia_Id__c'] ?? 'N/A';
            $estado = $payload['Estado_Nuevo__c'] ?? 'N/A';

            $this->line('['.now()->toTimeString()."] Lote #{$igniaId} -> {$estado}");

            HandleLoteActualizadoEvent::dispatch($payload);
        });

        $this->info('Escucha finalizada.');

        return self::SUCCESS;
    }
}
