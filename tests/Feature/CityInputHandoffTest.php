<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class CityInputHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        $this->product = Product::factory()->create([
            'name' => 'Shiitake',
            'price' => 25000,
            'is_active' => true,
            'stock' => 10
        ]);

        \App\Models\ShippingZone::create([
            'city' => 'Bogotá',
            'price' => 8000,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_handles_city_input_after_product_selection()
    {
        // 1. User selects product
        $context = [];
        $response = $this->aiService->processMessage("quiero shiitake", '127.0.0.1', $context);

        // Assert asked for city
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('ciudad', strtolower($response['message']));

        // 2. User answers "Bogota"
        // Context should be updated by service (simulating session perisistence via test helpers if needed, but service reads session)
        // Service writes to Session. We need to make sure subsequent calls read it.
        // processMessage reads session('ai_context').

        $response = $this->aiService->processMessage("Bogota", '127.0.0.1', []);

        // SHOULD NOT BE HANDOFF
        $this->assertNotEquals('handoff', $response['type']);

        // Should calculate shipping or ask for locality/confirmation
        // Bogota might need locality
        if (str_contains(strtolower($response['message']), 'localidad')) {
            $this->assertEquals('question', $response['type']);
        } else {
            // Or order confirmation/shipping info
            $this->assertTrue(
                $response['type'] === 'shipping_info' ||
                $response['type'] === 'order_confirmation' ||
                $response['type'] === 'answer'
            );
        }
    }
}
