<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

class HandoffRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        // Ensure we have a product
        Product::factory()->create([
            'name' => 'Melena de León',
            'stock' => 10,
            'is_active' => true,
            'price' => 50000
        ]);
    }

    /** @test */
    public function it_prompts_guest_for_registration_on_add_to_cart_intent()
    {
        // 1. Simulate Context: Product suggested/selected
        $product = Product::first();
        session(['ai_context' => ['last_product_id' => $product->id]]);

        // 2. Act: Guest user says "Me interesa, agregala al carrito"
        // This fails if the agent thinks it's a general question or falls back to handoff
        $response = $this->aiService->processMessage("Me interesa, agregala al carrito", '127.0.0.1', []);

        // 3. Assert:
        // SHOULD BE: 'question' (asking for name/email)
        // CURRENTLY FAILS AS: 'handoff' (because it doesn't recognize the intent and user is guest)

        $this->assertNotEquals('handoff', $response['type'], 'Should not handoff for sales intent even if guest.');
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('nombre', $response['message']);
        $this->assertStringContainsString('correo', $response['message']);

        // Ensure we are in "waiting for data" mode
        $this->assertTrue(session('ai_waiting_for_user_data'), 'Session should be waiting for user data');
    }

    /** @test */
    public function it_captures_user_info_instead_of_handoff_fallback_for_guest()
    {
        // 1. Simulate Context: General query
        session(['ai_context' => []]);

        // 2. Act: Guest user asks something complex or weird that triggers fallback
        // e.g. "Quiero hablar con alguien sobre un pedido especial" (Explicit handoff request)
        // OR just a confused bot fallback. 
        // Let's try standard fallback first.

        // Mock Notifications to ensure we DON'T send one immediately
        Notification::fake();

        $response = $this->aiService->processMessage("no entiendo nada, ayuda", '127.0.0.1', []);

        // 3. Assert: 
        // Logic change: If guest, ask for email BEFORE handoff.
        $this->assertNotEquals('handoff', $response['type'], 'Should catch fallback for guest.');
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('correo', $response['message']);

        Notification::assertNothingSent();
    }
}
