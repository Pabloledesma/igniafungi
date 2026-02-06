<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Category;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected $service;
    protected $geminiMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Gemini Client
        $this->geminiMock = \Mockery::mock(\App\Services\Ai\GeminiClient::class);
        $this->app->instance(\App\Services\Ai\GeminiClient::class, $this->geminiMock);

        // Seed
        ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Usaquén', 'price' => 8000]);

        $this->service = app(AiAgentService::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function city_persists_across_interactions_and_is_forced_in_checkout()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // 1. User says "Vivo en Bogotá Usaquén"
        // Intercepted by ShippingHandler because "Bogotá" is in DB.
        // Handlers: ShippingHandler matches. Gemini NOT called (mock unused here).
        $this->geminiMock->shouldReceive('setHistory')->zeroOrMoreTimes();

        // Note: IF ShippingHandler fails to catch it, this mock ensures we don't crash, 
        // but the assertion for session keys relies on ShippingHandler doing its job.

        $response = $this->service->processMessage("Vivo en Bogotá Usaquén", '127.0.0.1');

        // Assert Session has it
        $this->assertEquals('Bogotá', session('ai_context.city'));
        $this->assertEquals('Usaquén', session('ai_context.locality'));

        // 2. User asks for Product: "Quiero Melena"
        // Handlers: OrderHandler (no match), CatalogHandler (no match), ShippingHandler (no match).
        // Fallback to Gemini.

        $product = Product::factory()->create(['name' => 'Melena', 'price' => 50000, 'stock' => 10, 'is_active' => true]);

        // Mock Sequence for Step 2
        // Call A: "Quiero Melena" -> Gemini says "Action: GET_PRODUCT"
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->with(\Mockery::on(fn($arg) => str_contains((string) $arg, 'Quiero Melena')), \Mockery::any(), true)
            ->andReturn(json_encode([
                'needs_action' => true,
                'action_name' => 'GET_PRODUCT',
                'action_params' => ['product_name' => 'Melena']
            ]));

        // Call B: "Tool Output: ..." -> Gemini says "Cost is..."
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->with(\Mockery::on(fn($arg) => str_contains((string) $arg, 'Tool Output') || str_contains((string) $arg, 'SYSTEM_TOOL_OUTPUT')), \Mockery::any(), true)
            ->andReturn(json_encode([
                'needs_action' => false,
                'response' => 'Tenemos Melena. El costo de envío a Bogotá es de $8.000.'
            ]));

        $response = $this->service->processMessage("Quiero Melena", '127.0.0.1');

        // Should go STRAIGHT to Shipping/Order logic because city is known
        $this->assertStringNotContainsString('En qué ciudad te encuentras', $response['message']);
        $this->assertStringContainsString('costo de', $response['message']);

        // 3. User confirms order
        // Intercepted by OrderHandler ("Generar orden").
        // Checks context -> Confirmed IDs (Added by GET_PRODUCT tool above).
        $response = $this->service->processMessage("Generar orden", '127.0.0.1');

        // 4. Verify Checkout Session has the FORCED values
        $checkout = session('checkout_shipping');
        $this->assertNotNull($checkout, 'Checkout session should be set');
        $this->assertEquals('Bogotá', $checkout['city']);
        $this->assertEquals('Usaquén', $checkout['location']);
        $this->assertTrue($checkout['is_bogota']);
    }
}
