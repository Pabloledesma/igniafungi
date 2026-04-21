<?php

namespace Tests\Feature\Services;

use App\Services\SalesforceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SalesforceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('salesforce_token');

        config([
            'salesforce.client_id' => 'test-client-id',
            'salesforce.client_secret' => 'test-client-secret',
            'salesforce.username' => 'test@ignia.com',
            'salesforce.password' => 'testpass',
            'salesforce.security_token' => 'testtoken',
            'salesforce.login_url' => 'https://login.salesforce.com',
        ]);
    }

    private function fakeAuth(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token-abc123',
                'instance_url' => 'https://ignia.salesforce.com',
                'token_type' => 'Bearer',
            ], 200),
        ]);
    }

    #[Test]
    public function it_obtains_an_access_token_on_first_request(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*' => Http::response([], 204),
        ]);

        (new SalesforceService)->upsertBatch([
            'id' => 1, 'code' => 'MEL-1', 'strain' => 'Melena', 'status' => 'active',
            'type' => 'grain', 'inoculation_date' => '2025-04-20', 'quantity' => 10, 'initial_wet_weight' => 5,
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2/token'));
    }

    #[Test]
    public function it_caches_the_token_to_avoid_repeated_auth_calls(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'cached-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*' => Http::response([], 204),
        ]);

        $service = new SalesforceService;

        $service->upsertBatch([
            'id' => 1, 'code' => 'MEL-1', 'strain' => 'Melena', 'status' => 'active',
            'type' => 'grain', 'inoculation_date' => '2025-04-20', 'quantity' => 10, 'initial_wet_weight' => 5,
        ]);

        $service->upsertBatch([
            'id' => 2, 'code' => 'OST-1', 'strain' => 'Ostra', 'status' => 'active',
            'type' => 'bulk', 'inoculation_date' => '2025-04-21', 'quantity' => 20, 'initial_wet_weight' => 8,
        ]);

        // 1 auth + 2 upserts = 3 total (NOT 4, because token is cached)
        Http::assertSentCount(3);
    }

    #[Test]
    public function it_throws_when_salesforce_auth_fails(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Salesforce auth failed/');

        (new SalesforceService)->upsertBatch([
            'id' => 1, 'code' => 'X', 'strain' => null, 'status' => 'active',
            'type' => 'grain', 'inoculation_date' => null, 'quantity' => 5, 'initial_wet_weight' => 2,
        ]);
    }

    #[Test]
    public function it_sends_correct_payload_when_upserting_a_batch(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/Lote__c/*' => Http::response([], 204),
        ]);

        (new SalesforceService)->upsertBatch([
            'id' => 42,
            'code' => 'MEL-200425-1',
            'strain' => 'Melena de León',
            'status' => 'active',
            'type' => 'grain',
            'inoculation_date' => '2025-04-20',
            'quantity' => 30,
            'initial_wet_weight' => 10.5,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Lote__c/ignia_id__c/42')
                && $request->method() === 'PATCH'
                && $request['Codigo__c'] === 'MEL-200425-1'
                && $request['Cepa__c'] === 'Melena de León'
                && $request['Estado__c'] === 'active'
                && $request['Tipo__c'] === 'grain'
                && (float) $request['Peso_Inicial_Kg__c'] === 10.5
                && (int) $request['Cantidad__c'] === 30;
        });
    }

    #[Test]
    public function it_throws_when_batch_upsert_request_fails(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/Lote__c/*' => Http::response(['message' => 'NOT_FOUND'], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Salesforce upsert batch failed/');

        (new SalesforceService)->upsertBatch([
            'id' => 99, 'code' => 'X', 'strain' => null, 'status' => 'active',
            'type' => 'grain', 'inoculation_date' => null, 'quantity' => 5, 'initial_wet_weight' => 2,
        ]);
    }

    #[Test]
    public function it_sends_correct_payload_when_upserting_a_harvest(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/query*' => Http::response([
                'records' => [['Id' => 'a01SF000001XyZW']],
            ], 200),
            '*/Cosecha__c/*' => Http::response([], 204),
        ]);

        (new SalesforceService)->upsertHarvest([
            'id' => 7,
            'weight' => 0.850,
            'harvest_date' => '2025-04-20',
            'notes' => 'Primera cosecha de la temporada',
            'batch_id' => 42,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Cosecha__c/ignia_id__c/7')
                && $request->method() === 'PATCH'
                && (float) $request['Peso_Kg__c'] === 0.850
                && $request['Fecha_Cosecha__c'] === '2025-04-20'
                && $request['Lote__c'] === 'a01SF000001XyZW';
        });
    }

    #[Test]
    public function it_throws_when_harvest_upsert_request_fails(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/query*' => Http::response([
                'records' => [['Id' => 'a01SF000001XyZW']],
            ], 200),
            '*/Cosecha__c/*' => Http::response(['message' => 'SERVER_ERROR'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Salesforce upsert harvest failed/');

        (new SalesforceService)->upsertHarvest([
            'id' => 7, 'weight' => 0.5, 'harvest_date' => '2025-04-20', 'notes' => null, 'batch_id' => 42,
        ]);
    }

    #[Test]
    public function it_throws_when_lote_not_found_in_salesforce(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/query*' => Http::response(['records' => []], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no encontrado en Salesforce/');

        (new SalesforceService)->upsertHarvest([
            'id' => 7, 'weight' => 0.5, 'harvest_date' => '2025-04-20', 'notes' => null, 'batch_id' => 99,
        ]);
    }

    #[Test]
    public function it_handles_batch_with_null_strain_and_date_without_throwing(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token',
                'instance_url' => 'https://ignia.salesforce.com',
            ], 200),
            '*/Lote__c/*' => Http::response([], 204),
        ]);

        // Should not throw even with null optional fields
        (new SalesforceService)->upsertBatch([
            'id' => 1,
            'code' => 'GRA-200425-1',
            'strain' => null,
            'status' => 'active',
            'type' => 'grain',
            'inoculation_date' => null,
            'quantity' => 10,
            'initial_wet_weight' => 3.0,
        ]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'Lote__c'));
    }
}
