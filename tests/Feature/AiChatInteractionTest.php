<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiChatInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected AiAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiAgentService::class);

        // Seed Shipping Zones
        \App\Models\ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => 'Usaquén',
            'price' => 8000
        ]);
        \App\Models\ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => null,
            'price' => 10000
        ]);
        \App\Models\ShippingZone::create([
            'city' => 'Medellín',
            'locality' => null,
            'price' => 15000
        ]);
    }

    /** @test */
    /** @test */
    public function test_generates_link_directly_for_guests_deferring_registration()
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
        // Should NOT ask for registration (type != question about data)
        // Should return SYSTEM message with LINK
        $this->assertEquals('system', $response['type']);
        $this->assertStringContainsString('href=', $response['message']);
        $this->assertTrue(collect($response['actions'])->contains('type', 'link'));
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
        $userInput = "Soy Juan Perez, mi correo es juan@test.com";
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert
        // Should have created user
        $this->assertDatabaseHas('users', ['email' => 'juan@test.com', 'name' => 'Juan Perez']);
        $user = User::where('email', 'juan@test.com')->first();

        $this->assertAuthenticatedAs($user);

        // Should now be Order Confirmation (link)
        $this->assertEquals('system', $response['type']);

        // Verify Location Persistence
        $this->assertEquals('Bogotá', $user->city); // Assuming we add this field or profile
        // $this->assertEquals('Bogotá', session('checkout_shipping')['city']);
    }

    /** @test */
    public function it_includes_actions_in_shipping_query_response()
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        $context = ['city' => 'Medellín'];

        // Act
        $response = $this->service->processMessage('Costo envio a Medellin', '127.0.0.1', $context);

        // Assert
        $this->assertArrayHasKey('actions', $response);
        $this->assertCount(2, $response['actions']); // More products, Checkout
        $this->assertEquals('more_products', $response['actions'][0]['type']);
    }

    /** @test */
    public function it_persists_location_data_to_user_and_session()
    {
        // Arrange
        $context = [
            'confirmed_products' => [1],
            'city' => 'Medellín',
            'locality' => null // Medellin has no locality logic usually
        ];
        session(['ai_context' => $context]);
        session(['ai_waiting_for_user_data' => true]);

        // Act
        $userInput = "Maria, maria@test.com";
        $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert DB
        $user = User::where('email', 'maria@test.com')->first();
        $this->assertEquals('Medellín', $user->city);

        // Assert Session for CartPage
        $sessionData = session('checkout_shipping');
        $this->assertNotNull($sessionData, 'checkout_shipping session should be set');
        $this->assertEquals('Medellín', $sessionData['city']);
        $this->assertEquals(15000, $sessionData['cost']); // From seeded data
        $this->assertFalse($sessionData['is_bogota']);
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

        // Act
        $userInput = "Yoshitomo Wiskicito y mi correo es yoshi@tomo.com";
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // Assert
        $this->assertDatabaseHas('users', [
            'email' => 'yoshi@tomo.com',
            'name' => 'Yoshitomo Wiskicito'
        ]);

        // Assert message contains registration info
        $this->assertStringContainsString('registrado', strtolower($response['message']));
    }

    /** @test */
    public function it_persists_manual_city_input_to_session()
    {
        // Use Livewire component test
        \Livewire\Livewire::test(\App\Livewire\AiChat::class)
            ->set('city', 'Cartagena')
            ->assertSet('city', 'Cartagena');

        // Check Session
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

        // Update context to use this product
        $context = [
            'city' => 'Medellín',
            'last_product_id' => 999
        ];
        session(['ai_context' => $context]);

        // Authenticate User to pass validation
        $user = User::factory()->create();
        $this->actingAs($user);

        $userInput = "Cuanto el envio a Medellín";
        $response = $this->service->processMessage($userInput, '127.0.0.1', $context);

        // 3. Assert Interception
        $this->assertEquals('suggestion', $response['type']);
        $this->assertStringContainsString('no enviamos frescos', strtolower($response['message']));
        $this->assertArrayHasKey('payload', $response); // Alternatives
    }
    /** @test */
    public function test_remembers_city_context_during_registration_multiturn()
    {
        // 1. Setup: User wants product
        session([
            'ai_context' => ['confirmed_products' => [101], 'last_product_id' => 101],
            'ai_registration_data' => []
        ]);

        // 2. User says "Villao" (Inferred City)
        $response1 = $this->service->processMessage("Villao", '127.0.0.1');
        $this->assertStringContainsString('Villavicencio', $response1['message']);

        // Verify session has city in EXTENDED context (registration_data)
        $regData = session('ai_registration_data');
        $this->assertEquals('Villavicencio', $regData['city']);

        // 3. User gives Name/Email (No city mentioned)
        $response2 = $this->service->processMessage("Soy Elber, mi correo es elber@test.com", '127.0.0.1');

        // 4. Assert: Should NOT ask for city again.
        $this->assertStringNotContainsString('ciudad', strtolower($response2['message']));
        // Should confirm registration or order
        $this->assertTrue(in_array($response2['type'], ['system', 'suggestion']));
    }
}
