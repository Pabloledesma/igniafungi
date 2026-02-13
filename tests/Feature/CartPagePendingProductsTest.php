<?php

namespace Tests\Feature;

use App\Livewire\CartPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartPagePendingProductsTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create(['slug' => 'deshidratados', 'is_active' => true]);

        $this->product = Product::factory()->create([
            'name' => 'Melena Seca',
            'category_id' => $category->id,
            'price' => 35000,
            'stock' => 10,
            'is_active' => true,
        ]);

        ShippingZone::create(['city' => 'Bogotá', 'locality' => 'Engativá', 'price' => 9000]);
    }

    /** @test */
    public function it_loads_pending_cart_products_from_session_on_mount(): void
    {
        session()->put('pending_cart_products', [$this->product->id]);

        Livewire::test(CartPage::class)
            ->assertStatus(200)
            ->assertSet('cart_items', function ($items) {
                return count($items) === 1 && $items[0]['product_id'] === $this->product->id;
            });

        // Session key should be consumed (pulled)
        $this->assertNull(session('pending_cart_products'));
    }

    /** @test */
    public function it_loads_multiple_pending_products_from_session(): void
    {
        $product2 = Product::factory()->create([
            'name' => 'Reishi Seco',
            'price' => 25000,
            'stock' => 5,
            'is_active' => true,
        ]);

        session()->put('pending_cart_products', [$this->product->id, $product2->id]);

        Livewire::test(CartPage::class)
            ->assertSet('cart_items', function ($items) use ($product2) {
                if (count($items) !== 2) {
                    return false;
                }
                $ids = array_column($items, 'product_id');

                return in_array($this->product->id, $ids) && in_array($product2->id, $ids);
            });
    }

    /** @test */
    public function it_renders_normally_without_pending_products(): void
    {
        // No pending_cart_products in session
        Livewire::test(CartPage::class)
            ->assertStatus(200)
            ->assertSet('cart_items', []);
    }

    /** @test */
    public function it_loads_pending_products_with_checkout_shipping_context(): void
    {
        session()->put('pending_cart_products', [$this->product->id]);
        session()->put('checkout_shipping', [
            'is_bogota' => true,
            'city' => 'Bogotá',
            'location' => 'Engativá',
            'cost' => 9000,
            'delivery_date' => null,
        ]);

        Livewire::test(CartPage::class)
            ->assertSet('cart_items', function ($items) {
                return count($items) === 1 && $items[0]['product_id'] === $this->product->id;
            })
            ->assertSet('city', 'Bogotá')
            ->assertSet('location', 'Engativá');
    }

    /** @test */
    public function order_handler_sets_pending_cart_products_in_session(): void
    {
        $user = User::factory()->create(['city' => 'Bogotá']);
        $this->actingAs($user);

        $geminiMock = \Mockery::mock(\App\Services\Ai\GeminiClient::class);
        $geminiMock->shouldReceive('setHistory')->byDefault();
        $this->app->instance(\App\Services\Ai\GeminiClient::class, $geminiMock);

        session(['ai_context' => [
            'confirmed_products' => [$this->product->id],
            'city' => 'Bogotá',
            'locality' => 'Engativá',
        ]]);

        $service = app(AiAgentService::class);
        $response = $service->processMessage('generar orden', '127.0.0.1', []);

        $this->assertEquals('system', $response['type']);

        // Verify session has the pending cart products
        $pending = session('pending_cart_products');
        $this->assertNotNull($pending);
        $this->assertContains($this->product->id, $pending);
    }
}
