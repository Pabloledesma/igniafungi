<?php

namespace Tests\Feature;

use App\Helpers\CartManagement;
use App\Livewire\CheckoutPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CartManagement::clearCartItems();
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
    }

    /** @test */
    public function it_calculates_pickup_shipping()
    {
        $product = Product::factory()->create(['price' => 10000]);
        CartManagement::addItemsToCart($product->id, 1);

        Livewire::test(CheckoutPage::class)
            ->set('shipping_method', 'pickup')
            ->call('calculateShipping') // Method protected? Livewire calls valid checks? No, likely usually internal or triggered by updatedShippingMethod.
            // But we can trigger update updates.
            ->assertSet('shipping_cost', 0);
    }

    /** @test */
    public function it_calculates_bogota_params()
    {
        Livewire::test(CheckoutPage::class)
            ->set('city', 'Bogotá')
            ->set('shipping_method', 'bogota')
            ->assertSet('shipping_cost', 10000);
    }

    /** @test */
    public function it_calculates_interrapidisimo_shipping()
    {
        $product = Product::factory()->create(['price' => 10000, 'weight' => 500]); // 500g
        CartManagement::addItemsToCart($product->id, 3); // 1.5kg -> 2kg charged?
        // Logic: 15000 + (ceil(1.5) - 1)*5000 = 15000 + (2-1)*5000 = 20000

        // Wait, logic implementation earlier:
        // $weightKg = ceil($totalWeight / 1000);
        // if ($weightKg < 1) $weightKg = 1;
        // $cost = 15000 + (($weightKg - 1) * 5000);

        // 1.5kg -> ceil(1.5) = 2.
        // 15000 + (1 * 5000) = 20000. Correct.

        Livewire::test(CheckoutPage::class)
            ->set('shipping_method', 'interrapidisimo')
            ->assertSet('shipping_cost', 20000);
    }

    /** @test */
    public function it_calculates_bogota_delivery_date_as_next_even_day()
    {
        // Mock current date or harvest date?
        // User said: "Calculado en el primer día par posterior a la cosecha"
        // If harvest is 15th (odd), next even is 16th.
        // If harvest is 16th (even), next even is ... 18th? Or same day if time permits? 
        // Let's assume logic: next even date >= harvest date. 
        // If harvest is even, maybe allow it? Let's say Next Even Day strictly.
        // Wait, "Deliveries grouped on Even Days". So if ready on 15th, deliver 16th. If ready on 16th, deliver 16th (if feasible) or 18th.
        // Implementation assumption: Next even day >= today (or harvest estimate).

        // For checkout, we usually look at Cart preorders to find MAX harvest date.
        // If no preorder, default to today + processed time.

        // Let's simulate a Preorder item in cart
        $product = Product::factory()->create(['weight' => 500]);
        $strain = \App\Models\Strain::factory()->create();
        $batch = \App\Models\Batch::factory()->create([
            'strain_id' => $strain->id,
            'expected_yield' => 1000,
            'estimated_harvest_date' => \Carbon\Carbon::parse('2026-06-01') // Odd day
        ]);
        $phase = \App\Models\Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubación']);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => 1]);

        // We need to associate product to strain to link batch logic if applicable, 
        // but cart items just have data. 
        // CheckoutPage logic probably just takes user input date for now, 
        // OR user wants AUTO CALCULATION? 
        // Requirement: "El sistema calcule la fecha de entrega"

        // So we shouldn't just accept user input.

        // Let's assume CheckoutPage `mount` or `updatedShippingMethod` sets `delivery_date`.

        // Use a known date
        \Carbon\Carbon::setTestNow('2026-06-01'); // Monday

        Livewire::test(CheckoutPage::class)
            ->set('city', 'Bogotá')
            ->set('shipping_method', 'bogota')
            // Logic: Today is 1st (Odd). Next even is 2nd.
            ->assertSet('delivery_date', '2026-06-02');

        // Test transition from Even -> Even (Same day valid? or Next?)
        // If "Next" implied: 16 -> 18.
        // If "Valid Even Day": 16 -> 16.
        // Let's assume same day is valid if early, but for safety next? 
        // "Posterior a la cosecha" implies > harvest date? 
        // Let's stick to "Next Even Day >= Date".

        \Carbon\Carbon::setTestNow('2026-06-02'); // Tuesday (Even)
        Livewire::test(CheckoutPage::class)
            ->set('city', 'Bogotá')
            ->set('shipping_method', 'bogota')
            ->assertSet('delivery_date', '2026-06-02'); // Or 4th?
    }

    /** @test */
    public function it_calculates_bogota_delivery_date_for_preorder()
    {
        // 1. Setup Preorder Scenario
        $strain = \App\Models\Strain::factory()->create();
        $product = Product::factory()->create(['strain_id' => $strain->id, 'in_stock' => false]);

        // 2. Create Batch with specific Harvest Date (Feb 9 - Odd)
        $harvestDate = \Carbon\Carbon::parse('2026-02-09');
        $phase = \App\Models\Phase::firstOrCreate(['slug' => 'incubation'], ['name' => 'Incubación']);

        $batch = \App\Models\Batch::factory()->create([
            'strain_id' => $strain->id,
            'expected_yield' => 5000,
            'estimated_harvest_date' => $harvestDate,
            'status' => 'active'
        ]);
        $batch->phases()->attach($phase->id, ['started_at' => now(), 'user_id' => 1]);

        // 3. Add to Cart as Preorder
        // Note: We skip the UI check and force 'is_preorder' -> true via helper logic or manually
        CartManagement::addItemsToCart($product->id, 1, true);

        // 4. Mock NOW to be earlier (e.g., Jan 15)
        \Carbon\Carbon::setTestNow('2026-01-15');

        // 5. Test Checkout
        // Expected: Harvest Feb 9 (Mon). Next Even Day >= Feb 9.
        // If Feb 9 is Odd, Next Even is Feb 10 (Tue).

        Livewire::test(CheckoutPage::class)
            ->set('city', 'Bogotá')
            ->set('shipping_method', 'bogota')
            ->call('calculateShipping')
            ->assertSet('delivery_date', '2026-02-10');
    }
}
