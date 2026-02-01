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

    protected AiAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiAgentService();

        // Seed
        ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Usaquén', 'price' => 8000]);
    }

    /** @test */
    public function city_persists_across_interactions_and_is_forced_in_checkout()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // 1. User says "Bogotá Usaquén" (Infers location)
        $response = $this->service->processMessage("Vivo en Bogotá Usaquén", '127.0.0.1');

        // Assert Session has it
        $this->assertEquals('Bogotá', session('ai_context.city'));
        $this->assertEquals('Usaquén', session('ai_context.locality'));

        // 2. User asks for Product (Should NOT ask for city again)
        $product = Product::factory()->create(['name' => 'Melena', 'price' => 50000, 'stock' => 10, 'is_active' => true]);

        $response = $this->service->processMessage("Quiero Melena", '127.0.0.1');

        // Should go STRAIGHT to Shipping/Order logic because city is known
        // NOT "En qué ciudad te encuentras?"
        $this->assertStringNotContainsString('En qué ciudad te encuentras', $response['message']);
        $this->assertStringContainsString('costo de envío', $response['message']);

        // 3. User confirms order
        $response = $this->service->processMessage("Generar orden", '127.0.0.1');

        // 4. Verify Checkout Session has the FORCED values
        $checkout = session('checkout_shipping');
        $this->assertEquals('Bogotá', $checkout['city']);
        $this->assertEquals('Usaquén', $checkout['location']);
        $this->assertTrue($checkout['is_bogota']);
    }
}
