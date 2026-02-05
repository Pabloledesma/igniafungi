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

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai.drivers.gemini.api_key', 'test-key');

        // Seed Shipping Zone
        ShippingZone::create([
            'city' => 'Bogotá',
            'price' => 15000
        ]);
    }

    public function test_it_calls_gemini_api_and_returns_message()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Hola, soy Ignia Bot.']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new AiAgentService();
        $response = $service->processMessage('Hola', '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertEquals('Hola, soy Ignia Bot.', $response['message']);

        // Assert API was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generateContent');
        });
    }

    public function test_it_executes_shipping_tool()
    {
        // 1. First Call: Gemini asks for Tool
        // 2. Second Call: Gemini sees result and answers
        Http::fakeSequence()
            ->push([
                'candidates' => [['content' => ['parts' => [['text' => 'ACTION: GET_SHIPPING_PRICE {"city": "Bogota"}']]]]]
            ])
            ->push([
                'candidates' => [['content' => ['parts' => [['text' => 'El envío a Bogotá cuesta $15.000.']]]]]
            ]);

        // Mock DB for ShippingZone if needed, or assume empty/error handling
        // We'll trust the service handles DB misses gracefully or we can seed.
        // For this test, if DB is empty, getShippingInfo returns error, but the Tool Result will be "Error...".
        // The Loop should still continue.

        $service = new AiAgentService();
        $response = $service->processMessage('Precio a Bogota', '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertEquals('El envío a Bogotá cuesta $15.000.', $response['message']);

        // Check that history contains the System Output
        $history = session('ai_chat_history');
        // Structure: User, Model(Action), User(System Result), Model(Final)
        // Actually our history logic only saves User + Final Assistant.
        // Logic: $history[] = User; $history[] = Final;
        // The intermediate steps are ephemeral in the loop.

        $lastMsg = end($history);
        $this->assertEquals('El envío a Bogotá cuesta $15.000.', $lastMsg['content']);
    }
}
