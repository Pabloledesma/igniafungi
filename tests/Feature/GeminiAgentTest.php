<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ShippingZone;

class GeminiAgentTest extends TestCase
{
    use RefreshDatabase;

    protected $geminiMock;
    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Gemini Client
        $this->geminiMock = \Mockery::mock(\App\Services\Ai\GeminiClient::class);
        $this->app->instance(\App\Services\Ai\GeminiClient::class, $this->geminiMock);

        // Seed Shipping Zone
        ShippingZone::create(['city' => 'Bogotá', 'price' => 15000]);
        ShippingZone::create(['city' => 'Cali', 'price' => 10000]);

        $this->aiService = app(AiAgentService::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_it_calls_gemini_api_and_returns_message()
    {
        // Mock simple answer
        $this->geminiMock->shouldReceive('setHistory')->once();
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->andReturn(json_encode([
                'needs_action' => false,
                'response' => 'Hola, soy Ignia Bot.'
            ]));

        $response = $this->aiService->processMessage('Hola', '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertEquals('Hola, soy Ignia Bot.', $response['message']);
    }

    public function test_it_handles_shipping_query_via_handler()
    {
        // Handler intercepts, so Gemini mock should NOT be called
        $this->geminiMock->shouldReceive('generateContent')->never();

        $response = $this->aiService->processMessage('Precio envio Cali', '127.0.0.1');

        $this->assertEquals('system', $response['type']);
        $this->assertStringContainsString('El envío a Cali', $response['message']);
        $this->assertStringContainsString('10.000', $response['message']);
    }
}
