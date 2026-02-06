<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class AiAgentSecurityTest extends TestCase
{
    public function test_agent_blocks_requests_after_minute_limit_exceeded()
    {
        // Limit: 10 per minute
        RateLimiter::clear('ai_chat:127.0.0.1');

        $service = new AiAgentService();
        $context = []; // dummy context

        // Mock Http to avoid real API calls
        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Response']]]]]], 200)
        ]);

        // Hit 10 times (allowed)
        for ($i = 0; $i < 10; $i++) {
            $response = $service->processMessage('test message', $context, '127.0.0.1');
            $this->assertNotEquals('error', $response['type'], "Request $i should pass");
        }

        // 11th time (blocked)
        $response = $service->processMessage('excessive message', $context, '127.0.0.1');

        $this->assertEquals('error', $response['type']);
        $this->assertStringContainsString('Vas muy rápido', $response['message']);
    }

    public function test_agent_blocks_requests_after_daily_limit_exceeded()
    {
        // Limit: 50 per day
        RateLimiter::clear('ai_chat:127.0.0.1');
        RateLimiter::clear('ai_chat_daily:127.0.0.1');

        $service = new AiAgentService();
        $context = [];

        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Response']]]]]], 200)
        ]);

        // Hit 50 times (allowed by daily, let's bypass minute limit by clearing it inside loop if needed, 
        // or just hit the daily key directly to simulate state)

        // Simulation: Manually hitting the limiter to reach 50
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit('ai_chat_daily:127.0.0.1', 86400);
        }

        // 51st time
        $response = $service->processMessage('daily limit breaker', $context, '127.0.0.1');

        $this->assertEquals('error', $response['type']);
        $this->assertStringContainsString('límite diario', $response['message']);
    }
}
