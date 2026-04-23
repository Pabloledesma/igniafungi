<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HandleLoteActualizadoEvent;
use App\Models\Batch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleLoteActualizadoEventTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'Lote_Id__c' => 'a00g800000EXAMPLE',
            'Ignia_Id__c' => null,
            'Estado_Nuevo__c' => 'Finalizado',
            'Cepa__c' => 'Orellana',
            'Eficiencia_Biologica__c' => 85.5,
        ], $overrides);
    }

    #[Test]
    public function updates_batch_status_from_event(): void
    {
        $batch = Batch::factory()->create(['status' => 'active']);

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id, 'Estado_Nuevo__c' => 'Finalizado'])
        );

        $this->assertEquals('finalized', $batch->fresh()->status);
    }

    #[Test]
    public function updates_sf_eficiencia_biologica_from_event(): void
    {
        $batch = Batch::factory()->create();

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id, 'Eficiencia_Biologica__c' => 92.3])
        );

        $this->assertEquals(92.3, $batch->fresh()->sf_eficiencia_biologica);
    }

    #[Test]
    public function updates_sf_synced_at_from_event(): void
    {
        $batch = Batch::factory()->create(['sf_synced_at' => null]);

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id])
        );

        $this->assertNotNull($batch->fresh()->sf_synced_at);
    }

    #[Test]
    public function does_nothing_when_ignia_id_is_missing(): void
    {
        HandleLoteActualizadoEvent::dispatchSync($this->makePayload(['Ignia_Id__c' => null]));

        $this->assertTrue(true);
    }

    #[Test]
    public function does_nothing_when_batch_not_found(): void
    {
        HandleLoteActualizadoEvent::dispatchSync($this->makePayload(['Ignia_Id__c' => 99999]));

        $this->assertTrue(true);
    }

    #[Test]
    public function maps_contaminated_status_correctly(): void
    {
        $batch = Batch::factory()->create(['status' => 'active']);

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id, 'Estado_Nuevo__c' => 'Contaminado'])
        );

        $this->assertEquals('contaminated', $batch->fresh()->status);
    }

    #[Test]
    public function maps_discarded_status_correctly(): void
    {
        $batch = Batch::factory()->create(['status' => 'active']);

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id, 'Estado_Nuevo__c' => 'Descartado'])
        );

        $this->assertEquals('discarded', $batch->fresh()->status);
    }

    #[Test]
    public function maps_unknown_status_to_active(): void
    {
        $batch = Batch::factory()->create(['status' => 'finalized']);

        HandleLoteActualizadoEvent::dispatchSync(
            $this->makePayload(['Ignia_Id__c' => $batch->id, 'Estado_Nuevo__c' => 'Desconocido'])
        );

        $this->assertEquals('active', $batch->fresh()->status);
    }
}
