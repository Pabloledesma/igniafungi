<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SalesforceService
{
    private string $baseUrl = '';

    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $loginUrl;

    public function __construct()
    {
        $this->clientId = config('salesforce.client_id');
        $this->clientSecret = config('salesforce.client_secret');
        $this->loginUrl = config('salesforce.login_url', 'https://login.salesforce.com');
    }

    public function getToken(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget('salesforce_token');
        }

        return $this->getAccessToken();
    }

    private function getAccessToken(): array
    {
        return Cache::remember('salesforce_token', 3500, function () {
            $response = Http::asForm()->post("{$this->loginUrl}/services/oauth2/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Salesforce auth failed: '.$response->body());
            }

            return $response->json();
        });
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getAccessToken();
        $this->baseUrl = $token['instance_url'];

        return Http::withToken($token['access_token'])
            ->baseUrl("{$this->baseUrl}/services/data/v59.0/sobjects/");
    }

    private function queryClient(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getAccessToken();
        $this->baseUrl = $token['instance_url'];

        return Http::withToken($token['access_token'])
            ->baseUrl("{$this->baseUrl}/services/data/v59.0/");
    }

    private function findLoteSalesforceId(int $batchId): string
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->queryClient()->get('query', [
            'q' => "SELECT Id FROM Lote__c WHERE ignia_id__c = {$batchId} LIMIT 1",
        ]);

        $records = $response->json()['records'] ?? [];

        if ($response->failed() || empty($records)) {
            throw new RuntimeException("Lote con ignia_id__c={$batchId} no encontrado en Salesforce.");
        }

        return $records[0]['Id'];
    }

    private function apexClient(): \Illuminate\Http\Client\PendingRequest
    {
        $token = $this->getAccessToken();
        $this->baseUrl = $token['instance_url'];

        return Http::withToken($token['access_token'])
            ->baseUrl("{$this->baseUrl}/services/apexrest/ignia/");
    }

    /**
     * Apex REST devuelve el body como un string JSON codificado dos veces.
     * Este método normaliza la respuesta a un array PHP.
     */
    private function parseApexResponse(\Illuminate\Http\Client\Response $response, string $context): array
    {
        if ($response->failed()) {
            throw new RuntimeException("Salesforce {$context} failed: ".$response->body());
        }

        $raw = $response->body();
        $decoded = json_decode($raw, true);

        // Si el resultado es un string, está doblemente codificado
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (! ($decoded['success'] ?? false)) {
            throw new RuntimeException("Salesforce {$context} error: ".($decoded['error'] ?? 'unknown'));
        }

        return $decoded['data'] ?? [];
    }

    public function getLote(int $igniaId): array
    {
        $response = $this->apexClient()->get("lotes/{$igniaId}");

        return $this->parseApexResponse($response, 'getLote');
    }

    public function getAllLotes(): array
    {
        $response = $this->apexClient()->get('lotes/');

        return $this->parseApexResponse($response, 'getAllLotes');
    }

    public function patchLote(int $igniaId, array $data): array
    {
        $response = $this->apexClient()->patch("lotes/{$igniaId}", $data);

        return $this->parseApexResponse($response, 'patchLote');
    }

    public function patchLoteBySfId(string $sfId, array $data): array
    {
        $response = $this->apexClient()->patch("lotes/{$sfId}", $data);

        return $this->parseApexResponse($response, 'patchLoteBySfId');
    }

    public function upsertBatch(array $data): array
    {
        $igniaId = $data['id'];
        $payload = [
            'Codigo__c' => $data['code'],
            'Cepa__c' => $data['strain'],
            'Estado__c' => $data['status'],
            'Tipo__c' => $data['type'],
            'Fecha_Inoculacion__c' => $data['inoculation_date'],
            'Cantidad__c' => $data['quantity'],
            'Peso_Inicial_Kg__c' => $data['initial_wet_weight'],
        ];

        $response = $this->client()->patch(
            "Lote__c/ignia_id__c/{$igniaId}",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException('Salesforce upsert batch failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function upsertHarvest(array $data): array
    {
        $igniaId = $data['id'];
        $loteSalesforceId = $this->findLoteSalesforceId($data['batch_id']);

        $payload = [
            'Peso_Kg__c' => $data['weight'],
            'Fecha_Cosecha__c' => $data['harvest_date'],
            'Notas__c' => $data['notes'],
            'Lote__c' => $loteSalesforceId,
        ];

        $response = $this->client()->patch(
            "Cosecha__c/ignia_id__c/{$igniaId}",
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException('Salesforce upsert harvest failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
