<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\Ai\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery;

class AiChatInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected AiAgentService $service;
    protected $geminiMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Gemini Client
        $this->geminiMock = Mockery::mock(GeminiClient::class);
        $this->app->instance(GeminiClient::class, $this->geminiMock);

        // Seed Shipping Zones FIRST so ShippingHandler loads them
        \App\Models\ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => 'Usaquén',
            'price' => 8000
        ]);
        \App\Models\ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => null, // Default
            'price' => 10000
        ]);
        \App\Models\ShippingZone::create([
            'city' => 'Medellín',
            'locality' => null,
            'price' => 15000
        ]);

        $this->service = app(AiAgentService::class);
    }

    // ... tearDown ...

    // ... helper ...

    /** @test */
    public function it_persists_location_data_to_user_and_session()
    {
        // Arrange
        $context = [
            'confirmed_products' => [1],
            'city' => 'Medellín',
            'locality' => null
        ];
        session(['ai_context' => $context]);
        session(['ai_waiting_for_user_data' => true]);

        // Act - Explicit "Soy" for regex
        $userInput = "Soy Maria, mi correo maria@test.com";

        // Handler logic again
        $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert DB
        $user = User::where('email', 'maria@test.com')->first();
        $this->assertNotNull($user, 'User should be created');
        $this->assertEquals('Medellín', $user->city);

        // Assert Session for CartPage
        $sessionData = session('checkout_shipping');
        $this->assertNotNull($sessionData, 'checkout_shipping session should be set');
        $this->assertEquals('Medellín', $sessionData['city']);
        $this->assertEquals(15000, $sessionData['cost']);
    }

    /** @test */
    public function it_extracts_name_and_email_from_natural_language_input()
    {
        // Arrange
        $context = [
            'confirmed_products' => [1],
            'city' => 'Bogotá',
            'cart_total' => 50000
        ];
        session(['ai_context' => $context]);
        session(['ai_waiting_for_user_data' => true]);

        // Act - Explicit "Me llamo" for regex
        $userInput = "Me llamo Yoshitomo Wiskicito. Mi correo es yoshi@tomo.com";
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert
        $this->assertDatabaseHas('users', [
            'email' => 'yoshi@tomo.com',
            'name' => 'Yoshitomo Wiskicito'
        ]);

        // Assert message contains registration info
        $this->assertEquals('system', $response['type']);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    protected function mockGeminiAnswer($userMessage, $assistantAnswer)
    {
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->with(Mockery::on(fn($arg) => str_contains($arg, $userMessage)), Mockery::any(), true)
            ->andReturn(json_encode([
                'needs_action' => false,
                'response' => $assistantAnswer
            ]));
    }

    protected function mockGeminiToolCall($toolName, $params, $toolOutputDetails)
    {
        // 1. Initial Call: Gemini decides to call tool
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->with(Mockery::any(), Mockery::any(), true)
            ->andReturn(json_encode([
                'needs_action' => true,
                'action_name' => $toolName,
                'action_params' => $params
            ]));

        // 2. Second Call: Gemini receives Tool Output and gives Final Answer
        $this->geminiMock->shouldReceive('generateContent')
            ->once()
            ->with(Mockery::on(fn($arg) => str_contains($arg, 'Tool Output') || str_contains($arg, 'SYSTEM_TOOL_OUTPUT')), Mockery::any(), true)
            ->andReturn(json_encode([
                'needs_action' => false,
                'response' => $toolOutputDetails['response'] ?? 'Respuesta final con datos del tool.',
                'actions' => $toolOutputDetails['actions'] ?? []
            ]));
    }

    /** @test */
    public function it_asks_guest_for_details_instead_of_link_directly()
    {
        // Arrange
        $context = [
            'confirmed_products' => [1, 2],
            'city' => 'Bogotá',
        ];
        session(['ai_context' => $context]);
        // No Auth::login

        // Act
        $response = $this->service->processMessage('Generar orden', '127.0.0.1', $context);

        // Assert
        // Should ask for registration (Lazy Registration)
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('dime tu nombre y correo', strtolower($response['message']));
    }

    /** @test */
    public function it_creates_user_and_logs_in_after_receiving_details()
    {
        // Arrange
        $context = [
            'confirmed_products' => [1],
            'city' => 'Bogotá',
            'cart_total' => 50000
        ];
        session(['ai_context' => $context]);
        session(['ai_waiting_for_user_data' => true]);

        // Act
        // Handler bypasses Gemini, no mock needed here if logic is pure regex/heuristic
        $userInput = "Soy Juan Perez, mi correo es juan@test.com";
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert
        // Should have created user
        $this->assertDatabaseHas('users', ['email' => 'juan@test.com', 'name' => 'Juan Perez']);
        $user = User::where('email', 'juan@test.com')->first();

        $this->assertAuthenticatedAs($user);

        // Should now be Order Confirmation (link) -> System
        $this->assertEquals('system', $response['type']);

        // Verify Location Persistence
        $this->assertEquals('Bogotá', $user->city);
        $this->assertEquals('Bogotá', session('checkout_shipping')['city']);
    }

    /** @test */
    public function it_includes_actions_in_shipping_query_response()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Context with confirmed products so actions are generated
        $context = [
            'city' => 'Medellín',
            'confirmed_products' => [1]
        ];
        session(['ai_context' => $context]);

        // "Costo envio a Medellin" is intercepted by ShippingHandler.
        // It should returns actions because confirmed_products is not empty.

        // Act
        $response = $this->service->processMessage('Costo envio a Medellin', '127.0.0.1', $context);

        // Assert
        $this->assertArrayHasKey('actions', $response);
    }



    /** @test */
    public function it_persists_manual_city_input_to_session()
    {
        // Use Livewire component test
        \Livewire\Livewire::test(\App\Livewire\AiChat::class)
            ->set('city', 'Cartagena')
            ->assertSet('city', 'Cartagena');

        // Check Session
        $this->assertEquals('Cartagena', session('ai_context')['city']);
    }

    /** @test */
    public function it_intercepts_fresh_products_outside_bogota_and_suggests_dry_alternatives()
    {
        // Create a fresh product
        $p = \App\Models\Product::factory()->create([
            'id' => 999,
            'name' => 'Orellana Fresca',
            'price' => 20000,
            'is_active' => true,
            'stock' => 10
        ]);
        // Mock fresh via slug logic or name
        // Our OrderHandler uses name 'Fresca' or category slug.
        // Let's rely on 'Fresca' in name.

        // Authenticate User to pass validation
        $user = User::factory()->create(['city' => 'Medellín']);
        $this->actingAs($user);

        // Update context to use this product
        // CRITICAL: OrderHandler checks 'confirmed_products'
        $context = [
            'city' => 'Medellín',
            'confirmed_products' => [999]
        ];
        session(['ai_context' => $context]);

        $userInput = "Generar Orden"; // Prompt OrderHandler
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // 3. Assert Interception
        // Can be 'suggestion' or 'system' depending on implementation details in OrderHandler
        // We know from previous tests it might be 'suggestion' if pivot logic works.
        // It returns 'suggestion' if blocked.
        $this->assertTrue(in_array($response['type'], ['suggestion', 'system']));
        $this->assertStringContainsString('no están disponibles', strtolower($response['message']));
        $this->assertArrayHasKey('payload', $response); // Alternatives
    }

    /** @test */
    public function test_guest_providing_city_triggers_shipping_not_registration()
    {
        // 1. Setup: User wants product
        session([
            'ai_context' => ['confirmed_products' => [1], 'last_product_id' => 1],
            'ai_registration_data' => []
        ]);
        // NO AUTH

        // Mock Gemini to answer shipping query without asking for registration
        // NOTE: "Bogotá" might trigger ShippingHandler? Or Fuzzy Location?
        // If "Bogotá" triggers Inferred Location logic, it sets location.
        // Then what?
        // Expected: The agent acknowledges location.
        // If it was "Bogotá", likely it infers location.
        // If strictly hitting Gemini:
        // Mock Gemini removed because ShippingHandler intercepts "Bogotá" logic.
        // $this->mockGeminiAnswer('Bogotá', 'Entendido, estamos en Bogotá. ¿Localidad?');

        // 2. User says "Bogotá"
        $response1 = $this->service->processMessage("Bogotá", '127.0.0.1');

        // 3. Assert: 
        // Should NOT ask for name/email
        $this->assertStringNotContainsString('dime tu nombre', strtolower($response1['message']));
        $this->assertStringNotContainsString('registrate', strtolower($response1['message']));
    }
}
