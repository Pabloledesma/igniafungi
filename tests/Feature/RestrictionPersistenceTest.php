<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class RestrictionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        // Seed Data
        ShippingZone::create(['city' => 'Neiva', 'price' => 20000]);
        ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Usaquén', 'price' => 5000]);

        // Fresh Product
        Product::factory()->create([
            'id' => 101,
            'name' => 'Eryngii Fresco',
            'is_active' => true,
            'stock' => 10
        ]);

        // Dry Product (for suggestion)
        Product::factory()->create([
            'id' => 102,
            'name' => 'Orellana Seca',
            'is_active' => true,
            'stock' => 10
        ]);
    }

    /** @test */
    public function it_enforces_restriction_and_persists_session_after_late_registration()
    {
        // 1. Arrange: Context has Fresh Product
        $context = [
            'confirmed_products' => [101], // Eryngii
            'cart_total' => 32000
        ];
        session(['ai_context' => $context]);
        // State: Waiting for data (Name/Email provided, waiting for City)
        session(['ai_waiting_for_user_data' => true]);
        session(['ai_registration_data' => ['name' => 'Pablo', 'email' => 'pablo@test.com']]);

        // 2. Act: User provides "Neiva" (National city)
        $response = $this->aiService->processMessage("Vivo en Neiva", '127.0.0.1', $context);

        // 3. Assert Session Persistence
        $checkoutSession = session('checkout_shipping');
        $this->assertNotNull($checkoutSession, 'Checkout session should be set.');
        $this->assertEquals('Neiva', $checkoutSession['city'], 'Session city should correspond to input.');

        // 4. Assert Restriction Enforcement
        // Should NOT be 'system' (order link) or 'question' (unrelated)
        // Should be SUGGESTION (pivot)
        $this->assertEquals('suggestion', $response['type'], 'Should suggest alternatives and block checkount.');
        $this->assertStringContainsString('no están disponibles', strtolower($response['message']));
    }
}
