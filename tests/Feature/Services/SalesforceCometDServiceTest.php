<?php

namespace Tests\Feature\Services;

use App\Services\SalesforceCometDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesforceCometDServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $instanceUrl = 'https://test.salesforce.com';

    private string $accessToken = 'fake-access-token';

    private string $cometdClientId = 'cometd-client-abc123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'salesforce.client_id' => 'fake-client-id',
            'salesforce.client_secret' => 'fake-client-secret',
            'salesforce.login_url' => 'https://login.salesforce.com',
        ]);
    }

    private function fakeToken(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
        ]);
    }

    private function handshakePayload(): array
    {
        return [[
            'channel' => '/meta/handshake',
            'successful' => true,
            'clientId' => $this->cometdClientId,
            'version' => '1.0',
            'supportedConnectionTypes' => ['long-polling'],
        ]];
    }

    private function subscribePayload(string $channel): array
    {
        return [[
            'channel' => '/meta/subscribe',
            'successful' => true,
            'subscription' => $channel,
            'clientId' => $this->cometdClientId,
        ]];
    }

    #[Test]
    public function handshake_succeeds_and_stores_client_id(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::response($this->handshakePayload()),
        ]);

        $cometd = app(SalesforceCometDService::class);
        $cometd->handshake();

        Http::assertSent(fn ($req) => str_contains($req->url(), '/cometd/59.0/'));
        $this->assertTrue(true);
    }

    #[Test]
    public function handshake_throws_on_failure(): void
    {
        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::response([[
                'channel' => '/meta/handshake',
                'successful' => false,
                'error' => '403::Handshake denied',
            ]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CometD handshake failed/');

        app(SalesforceCometDService::class)->handshake();
    }

    #[Test]
    public function subscribe_includes_replay_extension(): void
    {
        $channel = '/event/LoteActualizado__e';

        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::sequence()
                ->push($this->handshakePayload())
                ->push($this->subscribePayload($channel)),
        ]);

        $cometd = app(SalesforceCometDService::class);
        $cometd->handshake();
        $cometd->subscribe($channel, -1);

        Http::assertSent(function ($request) use ($channel) {
            $body = $request->data();
            $msg = $body[0] ?? null;

            return $msg
                && $msg['channel'] === '/meta/subscribe'
                && $msg['subscription'] === $channel
                && ($msg['ext']['replay'][$channel] ?? null) === -1;
        });
    }

    #[Test]
    public function subscribe_throws_on_failure(): void
    {
        $channel = '/event/LoteActualizado__e';

        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::sequence()
                ->push($this->handshakePayload())
                ->push([[
                    'channel' => '/meta/subscribe',
                    'successful' => false,
                    'error' => '403::Subscribe denied',
                ]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CometD subscribe failed/');

        $cometd = app(SalesforceCometDService::class);
        $cometd->handshake();
        $cometd->subscribe($channel);
    }

    #[Test]
    public function poll_filters_out_meta_messages(): void
    {
        $channel = '/event/LoteActualizado__e';

        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::sequence()
                ->push($this->handshakePayload())
                ->push($this->subscribePayload($channel))
                ->push([
                    ['channel' => '/meta/connect', 'successful' => true, 'clientId' => $this->cometdClientId],
                    ['channel' => $channel, 'data' => ['payload' => ['Ignia_Id__c' => 42, 'Estado_Nuevo__c' => 'Finalizado']]],
                ]),
        ]);

        $cometd = app(SalesforceCometDService::class);
        $cometd->handshake();
        $cometd->subscribe($channel);

        $events = $cometd->poll();

        $this->assertCount(1, $events);
        $this->assertEquals($channel, $events[0]['channel']);
        $this->assertEquals(42, $events[0]['data']['payload']['Ignia_Id__c']);
    }

    #[Test]
    public function poll_returns_empty_when_no_data_events(): void
    {
        $channel = '/event/LoteActualizado__e';

        Http::fake([
            '*/oauth2/token' => Http::response([
                'access_token' => $this->accessToken,
                'instance_url' => $this->instanceUrl,
            ]),
            '*/cometd/59.0/' => Http::sequence()
                ->push($this->handshakePayload())
                ->push($this->subscribePayload($channel))
                ->push([
                    ['channel' => '/meta/connect', 'successful' => true, 'clientId' => $this->cometdClientId],
                ]),
        ]);

        $cometd = app(SalesforceCometDService::class);
        $cometd->handshake();
        $cometd->subscribe($channel);

        $events = $cometd->poll();

        $this->assertEmpty($events);
    }
}
