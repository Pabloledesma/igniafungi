<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category; // Correct Import
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategorySelectionTest extends TestCase
{
    use RefreshDatabase;

    protected $aiService;
    protected $category1;
    protected $category2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(AiAgentService::class);

        $this->category1 = Category::create(['name' => 'Hongos Frescos', 'is_active' => true, 'slug' => 'hongos-frescos']);
        $this->category2 = Category::create(['name' => 'Kits de Cultivo', 'is_active' => true, 'slug' => 'kits-cultivo']);

        Product::factory()->create([
            'name' => 'Pioppino',
            'category_id' => $this->category1->id, // Correct Field
            'price' => 20000,
            'is_active' => true,
            'stock' => 10
        ]);

        Product::factory()->create([
            'name' => 'Kit Orellana',
            'category_id' => $this->category2->id,
            'price' => 35000,
            'is_active' => true,
            'stock' => 5
        ]);
    }

    /** @test */
    public function it_lists_categories_on_availability_query()
    {
        $response = $this->aiService->processMessage("que productos tienen?", '127.0.0.1', []);

        $this->assertEquals('catalog', $response['type']);
        $this->assertStringContainsString('Hongos Frescos', $response['message']);
        $this->assertStringContainsString('Kits de Cultivo', $response['message']);
        $this->assertStringNotContainsString('Pioppino', $response['message']); // Should NOT list products yet

        // Verify Payload has Index
        $this->assertArrayHasKey('index', $response['payload'][0]);
        $this->assertEquals(1, $response['payload'][0]['index']);
    }

    /** @test */
    public function it_shows_products_when_category_selected()
    {
        // Simulate category context being set implicitly or explicitly
        // If user says "1", likely selection.

        // 1. Get Categories to set context
        $response = $this->aiService->processMessage("que productos tienen?", '127.0.0.1', []);

        // Simulate sending "1"
        $context = session('ai_context', []);
        $response = $this->aiService->processMessage("1", '127.0.0.1', $context);

        $this->assertEquals('catalog', $response['type']);
        $this->assertStringContainsString('Pioppino', $response['message']);
        $this->assertStringNotContainsString('Kit Orellana', $response['message']);
    }

    /** @test */
    public function it_does_not_ask_for_city_prematurely()
    {
        $response = $this->aiService->processMessage("que productos tienen?", '127.0.0.1', []);

        $this->assertStringNotContainsString('ciudad', strtolower($response['message']));
    }
}
