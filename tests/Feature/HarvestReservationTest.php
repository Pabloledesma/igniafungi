<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Strain;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Livewire\ProductDetailPage;
use App\Helpers\CartManagement;

class HarvestReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CartManagement::clearCartItems();
    }

    /** @test */
    public function it_adds_preorder_to_cart_without_creating_order()
    {
        $strain = Strain::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'strain_id' => $strain->id,
            'category_id' => $category->id,
            'in_stock' => false,
            'stock' => 0,
            'price' => 100000,
            'weight' => 500
        ]);

        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'status' => 'incubation',
            'expected_yield' => 5000,
            'quantity' => 10
        ]);

        $phase = \App\Models\Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubación']);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => 1]);

        // Assert DB has 0 orders before
        $this->assertDatabaseCount('orders', 0);

        Livewire::test(ProductDetailPage::class, ['slug' => $product->slug])
            ->set('quantity', 1)
            ->call('addToCart', $product->id)
            ->assertSet('quantity', 1);

        // Assert DB STILL has 0 orders (Cart aggregation only)
        $this->assertDatabaseCount('orders', 0);

        $cart = CartManagement::getCartItemsFromCookie();
        $this->assertCount(1, $cart);
        $this->assertTrue($cart[0]['is_preorder']);
        $this->assertEquals(90000, $cart[0]['unit_amount']); // 10% discount
    }

    /** @test */
    public function it_displays_real_batch_estimated_date()
    {
        $strain = Strain::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'strain_id' => $strain->id,
            'category_id' => $category->id,
            'in_stock' => false,
            'stock' => 0
        ]);

        $futureDate = now()->addDays(25);
        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'status' => 'incubation',
            'estimated_harvest_date' => $futureDate,
            'expected_yield' => 5000,
            'quantity' => 10
        ]);
        $phase = \App\Models\Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubación']);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => 1]);

        Livewire::test(ProductDetailPage::class, ['slug' => $product->slug])
            ->assertSee($futureDate->format('d/m/Y'));
    }

    /** @test */
    public function it_reserves_capacity_only_for_paid_orders()
    {
        $strain = Strain::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'strain_id' => $strain->id,
            'category_id' => $category->id,
            'in_stock' => false,
            'weight' => 1000 // 1kg
        ]);

        // Batch capacity: 2kg
        $batch = Batch::factory()->create([
            'strain_id' => $strain->id,
            'status' => 'incubation',
            'expected_yield' => 2000,
            'quantity' => 4
        ]);
        $phase = \App\Models\Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubación']);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => 1]);

        // 1. Pending Order (2kg) - Should NOT block capacity
        $order = \App\Models\Order::factory()->create(['status' => 'pending']);
        \App\Models\OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'is_preorder' => true
        ]);

        Livewire::test(ProductDetailPage::class, ['slug' => $product->slug])
            ->set('quantity', 1) // 1kg request
            ->call('addToCart', $product->id)
            ->assertSee('Cosecha apartada con éxito!'); // Success (2kg Yield > 1kg Request, pending ignored)

        CartManagement::clearCartItems();

        // 2. Paid Order (2kg) - Should BLOCK capacity
        $order->update(['status' => 'paid']);

        Livewire::test(ProductDetailPage::class, ['slug' => $product->slug])
            ->set('quantity', 1)
            ->call('addToCart', $product->id)
            ->assertSee('Lo sentimos, no hay suficiente capacidad proyectada'); // Fail (2kg Paid + 1kg Request > 2kg Yield)
    }
}
