<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Post;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class AiAgentRefinementsTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;
    protected $freshProduct;
    protected $dryProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        // Setup Products
        // Create explicit category to avoid factory collision
        $catFresh = Category::factory()->create(['slug' => 'cat-fresh-' . uniqid()]);

        $this->freshProduct = Product::factory()->create([
            'name' => 'Pioppino Fresco',
            'category_id' => $catFresh->id,
            'description' => 'El pioppino es un hongo de sabor intenso y textura firme.',
            'stock' => 10,
            'is_active' => true,
            'price' => 20000
        ]);

        // Mock specific category for coherence check if needed, but name check relies on 'fresco' string

        // Create explicit category to avoid factory collision
        $catDry = Category::factory()->create(['slug' => 'cat-dry-' . uniqid()]);

        $this->dryProduct = Product::factory()->create([
            'name' => 'Melena de León Seca',
            'category_id' => $catDry->id,
            'description' => 'Ideal para la memoria y concentración.',
            'stock' => 10,
            'is_active' => true,
            'price' => 35000
        ]);
    }

    /** @test */
    public function it_returns_informational_response_for_generic_query()
    {
        $response = $this->aiService->processMessage("Que es el pioppino?", '127.0.0.1', []);

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('El pioppino es un hongo', $response['message']);
    }

    /** @test */
    public function it_searches_posts_if_keywords_found()
    {
        $user = User::factory()->create();
        Post::create([
            'user_id' => $user->id,
            'product_id' => $this->freshProduct->id,
            'title' => 'Receta Pioppino',
            'summary' => 'Deliciosa pasta con pioppino.',
            'content' => 'Cocinar el pioppino con ajo...',
            'is_published' => true,
            'slug' => 'receta-pioppino-' . uniqid() // Ensure slug if needed
        ]);

        $response = $this->aiService->processMessage("Como cocinar pioppino?", '127.0.0.1', []);

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Receta Pioppino', $response['message']);
    }

    /** @test */
    public function it_prioritizes_information_over_restriction_warning()
    {
        // Context: Medellin (Restricted for fresh)
        $context = ['city' => 'Medellín'];
        session(['ai_context' => $context]);

        // Query about FRESH product properties
        $response = $this->aiService->processMessage("Que propiedades tiene el pioppino fresco?", '127.0.0.1', $context);

        // Should be ANSWER, not SUGGESTION (Warning)
        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('El pioppino es un hongo', $response['message']);
    }

    /** @test */
    public function it_enforces_restriction_on_sales_intent_outside_bogota()
    {
        // Context: Medellin
        $context = ['city' => 'Medellín'];
        session(['ai_context' => $context]);

        // Sales Intent
        $response = $this->aiService->processMessage("Quiero comprar pioppino fresco", '127.0.0.1', $context);

        // Should be SUGGESTION (Warning)
        $this->assertEquals('suggestion', $response['type']);
        $this->assertStringContainsString('solo podemos enviarte productos secos', $response['message']);
    }

    /** @test */
    public function it_handles_add_more_products_action()
    {
        $response = $this->aiService->processMessage("Quiero agregar más productos", '127.0.0.1', []);

        $this->assertEquals('catalog', $response['type']);
        $this->assertStringContainsString('Aquí tienes la lista de nuevo', $response['message']);
        $this->assertArrayHasKey('payload', $response);
    }

    /** @test */
    public function it_lists_categories_instead_of_product_for_general_inquiry()
    {
        Product::query()->delete();
        Category::query()->delete();

        // 1. Create a product that might trigger false positive with "hongos"
        Product::factory()->create([
            'name' => 'Sustrato para hongos',
            'description' => 'Sustrato especial.',
            'stock' => 10,
            'is_active' => true,
        ]);

        // 2. Create Categories with products
        $catFresh = \App\Models\Category::factory()->create(['name' => 'Hongos Frescos', 'slug' => 'hongos-gourmet']);
        Product::factory()->create(['category_id' => $catFresh->id, 'name' => 'Orellana', 'is_active' => true, 'stock' => 10]);

        $catDry = \App\Models\Category::factory()->create(['name' => 'Hongos Secos', 'slug' => 'deshidratados']);
        Product::factory()->create(['category_id' => $catDry->id, 'name' => 'Reishi', 'is_active' => true, 'stock' => 10]);

        // 3. Act: "que hongos tienen?"
        $response = $this->aiService->processMessage("que hongos tienen?", '127.0.0.1', []);

        // 4. Assert: Should be CATALOG (categories), NOT PRODUCT SUGGESTION
        $this->assertEquals('catalog', $response['type'], "Failed: Assumed product instead of showing catalog. Msg: " . $response['message']);
        $this->assertStringContainsString('tipos de hongos', $response['message']);
        // Verify we see categories
        $this->assertStringContainsString('Hongos Frescos (Gourmet y Medicina)', $response['message']);
        $this->assertStringContainsString('Hongos Deshidratados', $response['message']);
    }
    /** @test */
    public function it_asks_for_city_when_fresh_product_requested_without_context()
    {
        // Context: Empty
        session(['ai_context' => []]);

        // Request Fresh Product
        $response = $this->aiService->processMessage("Quiero pioppino fresco", '127.0.0.1', []);

        // Should NOT be a restriction message ("Veo que estás en .")
        // Should be a QUESTION asking for location
        $this->assertStringNotContainsString('Veo que estás en', $response['message'], "Failed: Agent assumed empty location and restricted.");
        $this->assertStringContainsString('ciudad', strtolower($response['message']), "Failed: Agent did not ask for city.");

        // Improve Check: Accept 'question' OR 'answer' type (since LLM text response is type 'answer')
        // The important part is the CONTENT.
        $this->assertTrue(in_array($response['type'], ['question', 'answer']), "Failed: Response type should be question or answer.");
    }

    /** @test */
    public function it_accumulates_and_generates_order_correctly()
    {
        // 1. Setup Context (Simulate previous steps: Product detected, Intention confirmed)
        $context = [
            'confirmed_products' => [$this->freshProduct->id],
            'city' => 'Bogotá',
            'locality' => 'Engativá'
        ];
        session(['ai_context' => $context]);

        // 2. Act: "Generate order"
        $response = $this->aiService->processMessage("generar orden", '127.0.0.1', $context);

        // 3. Assert Response Logic
        $this->assertEquals('system', $response['type']);
        $this->assertStringContainsString('He añadido los productos a tu carrito', $response['message']);
        $this->assertStringContainsString('Bogotá', $response['message']);

        // 4. Verification: Check Cart Helper or Session
        // Check session checkout_shipping
        $shipping = session('checkout_shipping');
        $this->assertEquals('Bogotá', $shipping['city']);
        $this->assertEquals('Engativá', $shipping['location']);

        // Check Items in Cart (Cookie based 'cart_items')
        // CartManagement uses Cookie 'cart_items'. Retrieve it.
        $cookie = \App\Helpers\CartManagement::getCartItemsFromCookie();
        $this->assertCount(1, $cookie, "Cart should have 1 item");
        $this->assertEquals($this->freshProduct->id, $cookie[0]['product_id']);
    }

    /** @test */
    public function it_uses_correct_price_from_tool()
    {
        // 1. Create unique product with specific price
        $product = Product::factory()->create([
            'name' => 'FungiTest Unique',
            'price' => 12345,
            'stock' => 10,
            'is_active' => true
        ]);

        // 2. Ask for price check
        // Note: The mocked agent (or real one) needs to deciding to use the tool.
        // Since we hit real Gemini API, we hope it calls CHECK_STOCK.
        $response = $this->aiService->processMessage("cuanto vale el FungiTest Unique?", '127.0.0.1', []);

        // 3. Assert
        $this->assertStringContainsString('12.345', $response['message'], "Failed: Agent did not report the correct price (12.345). Msg: " . $response['message']);
    }

    /** @test */
    public function it_provides_full_product_details_in_tool()
    {
        // 1. Create product with rich data
        $product = Product::factory()->create([
            'name' => 'Hongo Detalles',
            'description' => 'Descripción detallada del hongo curativo.',
            'short_description' => 'Resumen corto.',
            'is_active' => true,
            'stock' => 5
        ]);

        // 2. Call getProduct directly (Simulating tool use)
        $toolResult = $this->aiService->getProduct('Hongo Detalles');

        // 3. Assert keys exist
        $this->assertArrayHasKey('description', $toolResult);
        $this->assertArrayHasKey('short_description', $toolResult);
        $this->assertEquals('Descripción detallada del hongo curativo.', $toolResult['description']);
    }

    /** @test */
    public function it_includes_price_in_suggestion_payload()
    {
        // 1. Setup Context: User in Non-Bogotá city requesting Fresh Product
        session([
            'ai_context' => [
                'city' => 'Medellin',
                'confirmed_products' => [$this->freshProduct->id] // Fresh product
            ]
        ]);

        // 2. Mock Dry Product for Suggestion
        Product::factory()->create([
            'name' => 'Hongo Seco',
            'price' => 50000,
            'stock' => 10,
            'is_active' => true,
            'category_id' => \App\Models\Category::factory()->create(['slug' => 'hongos-medicinales'])->id
        ]);

        // 3. Trigger Order Confirmation (which should fail and suggest dry)
        // Access protected method via Reflection or simulate flow
        // Simulating flow via processMessage is hard because we need to hit the exact condition.
        // Easier: Use Reflection to call handleOrderConfirmation directly since it's protected.

        $reflection = new \ReflectionClass($this->aiService);
        $method = $reflection->getMethod('handleOrderConfirmation');
        $method->setAccessible(true);

        $response = $method->invoke($this->aiService, session('ai_context'));

        // 4. Assert
        $this->assertEquals('suggestion', $response['type']);
        $this->assertNotEmpty($response['payload']);
        $this->assertArrayHasKey('price', $response['payload'][0], "Payload missing 'price' key");
        // Value check is secondary, mainly we want to avoid "Undefined array key"
        $this->assertIsNumeric($response['payload'][0]['price']);
    }

    /** @test */
    public function it_captures_generemos_order_variation()
    {
        // 1. Setup Context
        $context = [
            'confirmed_products' => [$this->freshProduct->id],
            'city' => 'Bogotá',
            'locality' => 'Engativá'
        ];
        session(['ai_context' => $context]);

        // 2. Act: "generemos la orden porfavor" (User input from report)
        $response = $this->aiService->processMessage("generemos la orden porfavor", '127.0.0.1', $context);

        // 3. Assert Response Logic
        // IF broken, it will likely return 'answer' or 'question' (LLM bypass).
        // IF fixed, it returns 'system' (intercepted).
        $this->assertEquals('system', $response['type'], "Failed: 'generemos la orden' was not intercepted as order confirmation.");
    }

    /** @test */
    public function it_calculates_usme_shipping_correctly()
    {
        // 1. Ensure Usme exists in DB (Seed/Factory)
        // Check manually seeded zone or create one
        \App\Models\ShippingZone::updateOrCreate(
            ['city' => 'Bogotá', 'locality' => 'Usme'],
            ['price' => 19500]
        );

        // 2. Call Service directly
        $result = $this->aiService->getShippingInfo('Bogotá', 'Usme');

        // 3. Assert
        $this->assertEquals(19500, $result['price']);
        $this->assertEquals('Bogotá', $result['city']);
        $this->assertEquals('Usme', $result['locality']);
    }
}
