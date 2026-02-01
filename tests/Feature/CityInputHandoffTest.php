<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class CityInputHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        Product::factory()->create([
            'name' => 'Melena de León',
            'stock' => 10,
            'is_active' => true,
            'price' => 50000
        ]);

        \App\Models\ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Usaquén', 'price' => 5000]);
        \App\Models\ShippingZone::create(['city' => 'Cali', 'price' => 10000]);
    }

    /** @test */
    public function it_proceeds_to_shipping_check_if_guest_provides_city()
    {
        // 1. Simulate Context: Product selected 
        $product = Product::first();
        $context = ['last_product_id' => $product->id];
        session(['ai_context' => $context]);

        // 2. Act: User provides "Bogotá" (City)
        // Note: Bogota usually requires Locality check
        $response = $this->aiService->processMessage("Vivo en Bogotá", '127.0.0.1', []);

        // 3. Assert: Should NOT be Handoff. Should be Locality Prompt OR Shipping Quote (not Registration)
        $this->assertNotEquals('handoff', $response['type']);

        $msg = strtolower($response['message']);
        $this->assertStringNotContainsString('dime tu nombre', $msg);
        $this->assertStringNotContainsString('registrate', $msg);

        // Expect Locality prompt or cost
        $this->assertTrue(
            str_contains($msg, 'localidad') || str_contains($msg, 'costo') || str_contains($msg, 'envío'),
            "Expected locality or shipping cost message, got: " . $response['message']
        );
    }

    /** @test */
    public function it_continues_to_shipping_if_auth_user_provides_city()
    {
        // 1. Login User
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. Simulate Context: Product selected
        $product = Product::first();
        $context = ['last_product_id' => $product->id];
        session(['ai_context' => $context]);

        // 3. Act: User provides "Bogotá"
        $response = $this->aiService->processMessage("Bogotá", '127.0.0.1', []);

        // 4. Assert: Should be Shipping Calculation (Answer/Question depending on locality)
        // Since Bogotá needs locality, it might ask for it, OR calculated shipping if provided.
        // Let's assume just "Bogotá" triggers the flow.
        $this->assertNotEquals('handoff', $response['type']);
        // It might ask for locality or give price. 
        // If "Bogota", logic usually asks for locality.
        if ($response['type'] === 'question') {
            $this->assertStringContainsString('localidad', $response['message']);
        } else {
            $this->assertStringContainsString('envío', $response['message']);
        }
    }

    /** @test */
    public function it_sends_structured_slack_notification()
    {
        // Fake Notifications
        \Illuminate\Support\Facades\Notification::fake();

        // Simulate a context with product
        $product = Product::first();
        $context = ['last_product_id' => $product->id, 'city' => 'Cali'];
        session(['ai_context' => $context]);

        // Authenticate User to bypass guest safety net
        $user = User::factory()->create();
        $this->actingAs($user);

        // Trigger fallback
        $this->aiService->processMessage("ayuda humana por favor", '127.0.0.1', []);

        // Assert Notification Sent
        \Illuminate\Support\Facades\Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable,
            \App\Notifications\AiAgentHandoffNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['slack'] === config('services.slack.notifications.channel');
            }
        );
    }

    /** @test */
    public function it_handles_partial_registration_data()
    {
        // 1. Simulate Context: Waiting for data
        $product = Product::first();
        $context = ['last_product_id' => $product->id, 'city' => 'Bogotá'];
        session(['ai_context' => $context, 'ai_waiting_for_user_data' => true]);

        // 2. Act: User provides ONLY Name
        $response = $this->aiService->processMessage("Soy Pedro Ledesma", '127.0.0.1', []);

        // 3. Assert: Should ask for email, NOT handoff
        $this->assertNotEquals('handoff', $response['type']);
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('correo', $response['message']);

        // Ensure session is STILL waiting
        $this->assertTrue(session('ai_waiting_for_user_data'));
    }
}
