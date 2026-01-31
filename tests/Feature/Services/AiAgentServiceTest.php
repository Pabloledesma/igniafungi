<?php

namespace Tests\Feature\Services;

use App\Helpers\CartManagement;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AiAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AiAgentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiAgentService();

        // Seed basic Shipping Data
        ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => 'Usaquén',
            'price' => 8000
        ]);

        ShippingZone::create([
            'city' => 'Bogotá',
            'locality' => 'Chapinero',
            'price' => 7000
        ]);

        ShippingZone::create([
            'city' => 'Medellín',
            'locality' => null,
            'price' => 15000
        ]);

        ShippingZone::create([
            'city' => 'Cali',
            'locality' => null,
            'price' => 16000
        ]);
    }

    public function test_shipping_query_asks_locality_for_bogota()
    {
        $response = $this->service->processMessage("Envío a Bogotá", '127.0.0.1');

        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('localidad', $response['message']);
    }

    public function test_shipping_query_calculates_price_for_bogota_locality()
    {
        // Scenario 1: Direct full message
        // Note: Input needs to be close enough for fuzzy match working on words or full string
        $response = $this->service->processMessage("Envío a Bogotá Usaquén", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('8,000', $response['message']);
    }

    public function test_shipping_query_calculates_price_for_national_city()
    {
        $response = $this->service->processMessage("Envío a Medellín", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('15,000', $response['message']);
    }

    public function test_free_shipping_threshold()
    {
        // Mock Session with Cart Total > 200,000
        session(['ai_context' => ['cart_total' => 250000]]);

        $response = $this->service->processMessage("Costo de envío?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('GRATIS', $response['message']);
    }

    public function test_fresh_product_restriction_outside_bogota()
    {
        // Setup Fresh Product
        $category = Category::factory()->create(['name' => 'Hongos Frescos']);
        $product = Product::factory()->create([
            'name' => 'Orellana Fresca',
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10,
            'price' => 20000
        ]);

        // Setup Dry Alternative
        $dryCategory = Category::factory()->create(['name' => 'Deshidratados']);
        $dryProduct = Product::factory()->create([
            'name' => 'Orellana Seca',
            'category_id' => $dryCategory->id,
            'is_active' => true,
            'stock' => 10
        ]);

        // Set context to Medellin
        session(['ai_context' => ['city' => 'Medellín']]);

        // strict name match to ensure detection
        $response = $this->service->processMessage("Orellana Fresca", '127.0.0.1');

        // Should Suggest Dry
        $this->assertEquals('suggestion', $response['type']);
        // Note: The actual message contains "enviarte" instead of "enviar" in one path
        $this->assertStringContainsString('solo podemos enviar', strtolower($response['message']));
    }

    public function test_fresh_product_allowed_in_bogota()
    {
        $category = Category::factory()->create(['name' => 'Hongos Frescos']);
        $product = Product::factory()->create([
            'name' => 'Orellana Fresca',
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10,
            'price' => 20000
        ]);

        session(['ai_context' => ['city' => 'Bogotá', 'locality' => 'Chapinero']]);

        $response = $this->service->processMessage("Orellana Fresca", '127.0.0.1');

        // Should return question (confirming locality or asking for next step)
        $this->assertEquals('question', $response['type']);
        // The service asks for confirmation/locality even if set in context (current behavior)
        $this->assertStringContainsString('confirmar tu localidad', $response['message']);

        // Assert product is in session confirmed list
        $context = session('ai_context');
        $this->assertContains($product->id, $context['confirmed_products']);
    }

    public function test_availability_query_returns_stock()
    {
        Product::factory()->create(['name' => 'Melena de León', 'stock' => 5, 'is_active' => true]);
        Product::factory()->create(['name' => 'Reishi', 'stock' => 0, 'is_active' => true]); // No stock

        $response = $this->service->processMessage("¿Qué hongos tienen?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Melena de León', $response['message']);
        $this->assertStringNotContainsString('Reishi', $response['message']);
    }

    public function test_order_accumulation_and_confirmation()
    {
        // Create products
        $p1 = Product::factory()->create(['name' => 'Prod A', 'stock' => 10, 'is_active' => true]);
        $p2 = Product::factory()->create(['name' => 'Prod B', 'stock' => 10, 'is_active' => true]);

        // 1. User asks for Prod A
        $this->service->processMessage("Prod A", '127.0.0.1');

        // 2. User asks for Prod B
        $this->service->processMessage("Prod B", '127.0.0.1');

        $context = session('ai_context');
        $this->assertContains($p1->id, $context['confirmed_products']);
        $this->assertContains($p2->id, $context['confirmed_products']);

        // 3. Confirm Order
        $response = $this->service->processMessage("Listo", '127.0.0.1'); // "Listo" is affirmative

        $this->assertEquals('system', $response['type']);
        $this->assertStringContainsString('añadido los productos a tu carrito', $response['message']);
    }

    public function test_numeric_selection_adds_to_cart()
    {
        $p1 = Product::factory()->create(['name' => 'Melena', 'stock' => 5, 'is_active' => true]);
        // Set context manually to simulate pending suggestion
        session(['ai_context' => ['pending_suggestion_products' => [$p1->id]]]);

        // 2. Select option "1"
        $response = $this->service->processMessage("1", '127.0.0.1');

        $context = session('ai_context');
        $this->assertEquals($p1->id, $context['last_product_id']);
        $this->assertContains($p1->id, $context['confirmed_products']);
    }

    public function test_product_query_returns_description()
    {
        $category = Category::factory()->create(['name' => 'Hongos Frescos']);
        $product = Product::factory()->create([
            'name' => 'Melena de León',
            'description' => 'Ayuda a la memoria y regeneración neuronal.',
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10
        ]);

        // User asks "Que es Melena de León?"
        $response = $this->service->processMessage("Que es Melena de León", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Ayuda a la memoria', $response['message']);
    }

    public function test_product_query_fallback_to_posts()
    {
        $category = Category::factory()->create(['name' => 'Hongos Medicinales']);
        $product = Product::factory()->create([
            'name' => 'Reishi',
            'description' => 'Hongo de la inmortalidad.',
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10
        ]);

        // Post with extra info
        $user = \App\Models\User::factory()->create();
        \App\Models\Post::create([
            'title' => 'Beneficios del Reishi',
            'slug' => 'beneficios-reishi',
            'summary' => 'Reduce el estrés y mejora el sueño.',
            'content' => 'El Reishi es conocido por sus propiedades para calmar el sistema nervioso.',
            'is_published' => true,
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);

        // User asks about "nervioso" which is NOT in description but IS in post (content: sistema nervioso)
        $response = $this->service->processMessage("El Reishi sirve para el sistema nervioso?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Reduce el estrés', $response['message']);
        $this->assertStringContainsString('Beneficios del Reishi', $response['message']);
    }

    public function test_product_query_handoff_when_info_missing()
    {
        $category = Category::factory()->create(['name' => 'Hongos Medicinales']);
        $product = Product::factory()->create([
            'name' => 'Cordyceps',
            'description' => 'Aumenta la energía y resistencia.',
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10
        ]);

        // Query about something totally unrelated (e.g., flight)
        $response = $this->service->processMessage("El Cordyceps sirve para volar?", '127.0.0.1');

        $this->assertEquals('handoff', $response['type']);
        $this->assertStringContainsString('notificado a un agente humano', $response['message']);
    }

    public function test_detect_product_with_partial_name_in_sentence()
    {
        $product = Product::factory()->create([
            'name' => 'Melena de León Fresca (500g)',
            'description' => 'Ayuda a la concentración.',
            'is_active' => true,
            'stock' => 10
        ]);

        // User asks "Que es la melena de león?" (Product name has extra words)
        $response = $this->service->processMessage("no se que es la melena de león. Para que sirve?", '127.0.0.1');

        // Should NOT be handoff. Should be description.
        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Ayuda a la concentración', $response['message']);
    }

    public function test_product_query_with_conversational_fillers()
    {
        $product = Product::factory()->create([
            'name' => 'Melena de León',
            'description' => 'Ayuda a la concentración.',
            'stock' => 10,
            'is_active' => true
        ]);

        // "Me interesa" should be ignored as a keyword
        $response = $this->service->processMessage("Me interesa la Melena de León. Que es?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Ayuda a la concentración', $response['message']);
    }

    public function test_product_query_contextual_followup()
    {
        $product = Product::factory()->create([
            'name' => 'Melena de León',
            'description' => 'Ayuda a la concentración.',
            'stock' => 10,
            'is_active' => true
        ]);

        // Create a post about cooking/recipe
        \App\Models\Post::create([
            'title' => 'Recetas con Melena',
            'slug' => 'recetas-melena',
            'summary' => 'Deliciosa en el sartén.',
            'content' => 'Puedes usarla en la cocina salteada.',
            'is_published' => true,
            'user_id' => \App\Models\User::factory()->create()->id,
            'product_id' => $product->id
        ]);

        // Simulate session context (Product previously discussed)
        session(['ai_context' => ['last_product_id' => $product->id]]);

        // Query doesn't name product, just "usarla" (use it) or "cocina"
        $response = $this->service->processMessage("como puedo usarla en la cocina?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Deliciosa en el sartén', $response['message']);
    }
}
