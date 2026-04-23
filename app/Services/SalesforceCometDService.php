<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SalesforceCometDService
{
    private string $clientId = '';

    private int $messageId = 0;

    private string $instanceUrl = '';

    private string $accessToken = '';

    /** @var array<string, int> channel => replayId */
    private array $subscriptions = [];

    public function __construct(private readonly SalesforceService $salesforce) {}

    private function nextId(): string
    {
        return (string) ++$this->messageId;
    }

    private function cometdUrl(): string
    {
        return "{$this->instanceUrl}/cometd/59.0/";
    }

    /** @return array<int, array<string, mixed>> */
    private function post(array $messages): array
    {
        $response = Http::timeout(120)
            ->withToken($this->accessToken)
            ->post($this->cometdUrl(), $messages);

        if ($response->status() === 401) {
            $token = $this->salesforce->getToken(forceRefresh: true);
            $this->instanceUrl = $token['instance_url'];
            $this->accessToken = $token['access_token'];

            $response = Http::timeout(120)
                ->withToken($this->accessToken)
                ->post($this->cometdUrl(), $messages);
        }

        if ($response->failed()) {
            throw new RuntimeException('CometD request failed: '.$response->body());
        }

        return $response->json();
    }

    public function handshake(): void
    {
        $token = $this->salesforce->getToken();
        $this->instanceUrl = $token['instance_url'];
        $this->accessToken = $token['access_token'];

        $response = $this->post([[
            'id' => $this->nextId(),
            'channel' => '/meta/handshake',
            'version' => '1.0',
            'minimumVersion' => '1.0',
            'supportedConnectionTypes' => ['long-polling'],
        ]]);

        $handshake = $response[0];

        if (! ($handshake['successful'] ?? false)) {
            throw new RuntimeException('CometD handshake failed: '.json_encode($handshake));
        }

        $this->clientId = $handshake['clientId'];
    }

    public function subscribe(string $channel, int $replayId = -1): void
    {
        $this->subscriptions[$channel] = $replayId;

        $response = $this->post([[
            'id' => $this->nextId(),
            'channel' => '/meta/subscribe',
            'subscription' => $channel,
            'clientId' => $this->clientId,
            'ext' => [
                'replay' => [$channel => $replayId],
            ],
        ]]);

        $result = collect($response)->firstWhere('channel', '/meta/subscribe');

        if (! ($result['successful'] ?? false)) {
            throw new RuntimeException('CometD subscribe failed: '.json_encode($result));
        }
    }

    /** @return array<int, array<string, mixed>> Only non-meta messages */
    public function poll(): array
    {
        $response = $this->post([[
            'id' => $this->nextId(),
            'channel' => '/meta/connect',
            'connectionType' => 'long-polling',
            'clientId' => $this->clientId,
        ]]);

        return array_values(array_filter(
            $response,
            fn ($msg) => ! str_starts_with($msg['channel'] ?? '', '/meta/')
        ));
    }

    public function listen(string $channel, callable $callback): void
    {
        $running = true;

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$running) {
                $running = false;
            });
            pcntl_signal(SIGTERM, function () use (&$running) {
                $running = false;
            });
        }

        while ($running) {
            try {
                foreach ($this->poll() as $event) {
                    if (($event['channel'] ?? '') === $channel) {
                        $callback($event);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('CometD poll error: '.$e->getMessage());

                if (! $running) {
                    break;
                }

                sleep(5);

                try {
                    $this->handshake();

                    foreach ($this->subscriptions as $ch => $replayId) {
                        $this->subscribe($ch, $replayId);
                    }
                } catch (\Throwable $reconnectError) {
                    Log::error('CometD reconnect failed: '.$reconnectError->getMessage());
                }
            }
        }
    }
}
