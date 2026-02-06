<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class StrictRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed Data
        ShippingZone::create(['city' => 'Villavicencio', 'price' => 15000]);
        ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Usaquén', 'price' => 5000]);

        Product::factory()->create(['name' => 'Orellana Florida', 'is_active' => true, 'stock' => 10]);

        $this->aiService = app(AiAgentService::class);
    }

    /** @test */
    public function it_resolves_villao_as_villavicencio()
    {
        // 1. Act: User says "Estoy en Villao"
        // Note: The service uses inferLocationFromContent.
        // We simulate a context where we might be asking for shipping or just stating location.
        $response = $this->aiService->processMessage("Estoy en Villao", '127.0.0.1', []);

        // 2. Assert: Session has resolving city
        $context = session('ai_context');
        $this->assertEquals('Villavicencio', $context['city'] ?? null, 'Should resolve Villao to Villavicencio');
    }

    /** @test */
    public function it_does_not_create_user_with_only_email()
    {
        // 1. Simulate Context: Waiting for data
        session(['ai_waiting_for_user_data' => true, 'ai_context' => ['city' => 'Villavicencio']]);

        // 2. Act: User gives email ONLY
        $response = $this->aiService->processMessage("mi correo es pepe@test.com", '127.0.0.1', []);

        // 3. Assert:
        // - User NOT created
        $this->assertDatabaseMissing('users', ['email' => 'pepe@test.com']);
        // - Response asks for Name
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('nombre', strtolower($response['message']));

        // - Context has partial email
        // (Implementation detail: we might store it in ai_registration_data or context)
    }

    /** @test */
    public function it_creates_user_only_when_all_data_present()
    {
        // Setup
        session(['ai_waiting_for_user_data' => true, 'ai_context' => ['city' => 'Villavicencio']]);

        // 1. Give Email
        $this->aiService->processMessage("email: pepe@test.com", '127.0.0.1');

        // 2. Give Name
        $response = $this->aiService->processMessage("Soy Pepe Perez", '127.0.0.1');

        // 3. Assert User Created
        $this->assertDatabaseHas('users', [
            'email' => 'pepe@test.com',
            'name' => 'Pepe Perez',
            'city' => 'Villavicencio'
        ]);

        // 4. Response is success/handoff to order
        $this->assertStringContainsString('registrado', strtolower($response['message']));
    }
}
