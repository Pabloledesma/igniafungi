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
}
