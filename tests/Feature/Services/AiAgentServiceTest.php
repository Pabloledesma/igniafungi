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
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->service->processMessage("Envío a Bogotá", '127.0.0.1');

        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('localidad', $response['message']);
    }

    public function test_shipping_query_calculates_price_for_bogota_locality()
    {
        // Scenario 1: Direct full message
        // Note: Input needs to be close enough for fuzzy match working on words or full string

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->service->processMessage("Envío a Bogotá Usaquén", '127.0.0.1');

        $this->assertEquals('order_closure', $response['type']);
        $this->assertStringContainsString('8,000', $response['message']);
    }

    public function test_shipping_query_calculates_price_for_national_city()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->service->processMessage("Envío a Medellín", '127.0.0.1');

        $this->assertEquals('order_closure', $response['type']);
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
        $this->assertStringContainsString('no enviamos frescos allí', strtolower($response['message']));
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

        // Should return order_closure as we have city + locality
        $this->assertEquals('order_closure', $response['type']);
        // The service should calculate price directly
        $this->assertStringContainsString('costo de envío', $response['message']);

        // Assert product is in session confirmed list
        $context = session('ai_context');
        $this->assertContains($product->id, $context['confirmed_products']);
    }

    public function test_availability_query_returns_stock()
    {
        $cat = Category::factory()->create(['name' => 'Hongos Medicinales']);
        Product::factory()->create(['name' => 'Melena de León', 'stock' => 5, 'is_active' => true, 'category_id' => $cat->id]);
        Product::factory()->create(['name' => 'Reishi', 'stock' => 0, 'is_active' => true, 'category_id' => $cat->id]); // No stock

        $response = $this->service->processMessage("¿Qué hongos tienen?", '127.0.0.1');

        $this->assertEquals('catalog', $response['type']);
        $this->assertStringContainsString('Hongos Medicinales', $response['message']);
    }

    public function test_order_accumulation_and_confirmation()
    {
        // Create products
        $p1 = Product::factory()->create(['name' => 'Prod A', 'stock' => 10, 'is_active' => true]);
        $p2 = Product::factory()->create(['name' => 'Prod B', 'stock' => 10, 'is_active' => true]);

        // 1. Authenticate
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

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

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Aumenta la energía', $response['message']);
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

    public function test_product_query_synonym_expansion()
    {
        $product = Product::factory()->create([
            'name' => 'Melena de León',
            'description' => 'Ayuda a la concentración.',
            'stock' => 10,
            'is_active' => true
        ]);

        // Create a post about RECETAS (Synonym for Prepara)
        \App\Models\Post::create([
            'title' => 'Recetas con Melena',
            'slug' => 'recetas-melena',
            'summary' => 'Deliciosa en el sartén.',
            'content' => 'Uso culinario.',
            'is_published' => true,
            'user_id' => \App\Models\User::factory()->create()->id,
            'product_id' => $product->id
        ]);

        // Query uses "prepara" (not in post title/content explicitly, but triggers expansion)
        // Wait, "prepara" is synonymous with "receta"? 
        // My logic: if query has "prepara", merge "receta", "cocina" etc.
        // So search will look for "receta" and find the post.

        $response = $this->service->processMessage("Como se prepara la melena?", '127.0.0.1');

        $this->assertEquals('answer', $response['type']);
        $this->assertStringContainsString('Recetas con Melena', $response['message']);
    }


    public function test_fresh_product_restriction_on_shipping_query_with_context()
    {
        \App\Models\ShippingZone::firstOrCreate(
            ['city' => 'Popayán'],
            ['price' => 20000]
        );

        $catFresh = Category::firstOrCreate(
            ['slug' => 'hongos-gourmet'],
            ['name' => 'Hongos Gourmet', 'is_active' => true]
        );

        $catDry = Category::firstOrCreate(
            ['slug' => 'deshidratados'],
            ['name' => 'Hongos Deshidratados', 'is_active' => true]
        );

        $freshProduct = Product::factory()->create([
            'name' => 'Melena Fresca',
            'category_id' => $catFresh->id,
            'is_active' => true,
            'stock' => 5
        ]);

        $dryProduct = Product::factory()->create([
            'name' => 'Melena Seca',
            'category_id' => $catDry->id,
            'is_active' => true,
            'stock' => 5
        ]);

        // Scenario: User already selected Fresh product
        // Authenticate
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        session([
            'ai_context' => [
                'confirmed_products' => [$freshProduct->id],
                'last_product_id' => $freshProduct->id
            ]
        ]);

        // User asks for shipping to Popayan
        $response = $this->service->processMessage("Hacen domicilios a Popayan?", '127.0.0.1');

        // Should NOT show price. Should warn about freshness.
        $this->assertEquals('suggestion', $response['type']);
        $this->assertStringContainsString('no enviamos frescos allí', $response['message']);
        $this->assertStringContainsString('Melena Seca', $response['message']); // Should suggest dry
    }

    public function test_numeric_selection_on_clean_session_asks_city()
    {
        // 1. Setup products
        $product = Product::factory()->create(['name' => 'Melena', 'price' => 50000, 'stock' => 10, 'is_active' => true]);

        // 2. Clear Session
        session(['ai_context' => []]);

        // 3. User asks list (Populates pending suggestions)
        // We simulate this by setting pending suggestions manually or running the query
        // Let's run the query to be sure
        session(['ai_context' => ['pending_suggestion_products' => [$product->id]]]);

        // Assert context has pending suggestions
        $context = session('ai_context');
        $this->assertNotEmpty($context['pending_suggestion_products']);

        // 4. User selects "1"
        $response = $this->service->processMessage("1", '127.0.0.1');

        // 5. Expect Question about City (because Context has NO city)
        $this->assertEquals('question', $response['type']);
        $this->assertStringContainsString('ciudad', $response['message']);
        $this->assertStringNotContainsString('Popayán', $response['message']);
    }


    public function test_checkout_session_has_city()
    {
        // 1. Setup
        $product = Product::factory()->create(['name' => 'Melena', 'price' => 50000, 'stock' => 10, 'is_active' => true]);

        // 2. Set Context with City (Simulate previous "Envio a Cali")
        session([
            'ai_context' => [
                'confirmed_products' => [$product->id],
                'city' => 'Cali',
                'locality' => null
            ]
        ]);

        // Authenticate
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // 3. User says "Generar orden"
        $response = $this->service->processMessage("Generar orden", '127.0.0.1');



        // 4. Assert Session 'checkout_shipping' has Cali
        $shipping = session('checkout_shipping');
        $this->assertNotNull($shipping, 'Checkout shipping session not set');
        $this->assertEquals('Cali', $shipping['city']);

        // 5. Assert Message contains Link in Actions
        $linkAction = collect($response['actions'])->firstWhere('type', 'link');
        $this->assertNotNull($linkAction, 'Link action not found');
        $this->assertStringContainsString('/cart', $linkAction['url']);
    }
    public function test_agent_answers_questions_even_if_product_selected_and_city_missing()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // 1. User selects product
        $product = Product::factory()->create(['name' => 'Pioppino', 'stock' => 10, 'is_active' => true]);

        // Simulate selection context
        session([
            'ai_context' => [
                'last_product_id' => $product->id,
                'cart' => [],
                // 'city' => null // City is missing
            ]
        ]);

        // 2. User asks a question
        $response = $this->service->processMessage("No se como se cocinan los pioppino, podrias darme algunos tips?", '127.0.0.1');

        // 3. Expect answer, not question (city prompt)
        $this->assertEquals('answer', $response['type']);
        $this->assertStringNotContainsString('ciudad te encuentras', $response['message']);
    }
}
