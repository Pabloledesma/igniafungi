<?php

namespace Tests\Feature;

use App\Helpers\CartManagement;
use App\Livewire\CheckoutPage;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CouponSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cart cookies before each test
        CartManagement::clearCartItems();
    }

    /** @test */
    public function it_can_apply_a_valid_coupon()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['price' => 100000]);
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'active' => true,
        ]);

        Livewire::test(CheckoutPage::class)
            ->set('city', 'Medellín') // Sets default shipping to 45000 (unless logic changes)
            ->set('coupon_code_input', 'TEST10')
            ->call('applyCoupon')
            ->assertSet('applied_coupon_code', 'TEST10')
            ->assertSet('discount_amount', 10000) // 10% of 100,000
            ->assertSeeHtml('TEST10')
            ->assertSeeHtml('10,000'); // Check if discount is visible
    }

    /** @test */
    public function it_calculates_grand_total_correctly_with_coupon()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['price' => 100000]);
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'FIXED20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'active' => true,
        ]);

        // Shipping for Medellín is now Interrapidísimo (Variable). 
        // Product default weight < 1kg -> 15000.
        $shipping = 15000;
        $subtotal = 100000;
        $discount = 20000;
        $expectedGrandTotal = ($subtotal - $discount) + $shipping;

        Livewire::test(CheckoutPage::class)
            ->set('city', 'Medellín')
            ->set('coupon_code_input', 'FIXED20K')
            ->call('applyCoupon')
            ->assertSet('discount_amount', 20000)
            ->assertSet('shipping_cost', $shipping)
            ->assertSet('grand_total', $expectedGrandTotal);
    }

    /** @test */
    public function it_shows_error_for_invalid_coupon()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CheckoutPage::class)
            ->set('coupon_code_input', 'INVALID')
            ->call('applyCoupon')
            ->assertSet('applied_coupon_code', null)
            ->assertSet('discount_amount', 0);
        // Alert logic might be hard to test if using Jantinnerezo/livewire-alert without specific testing helpers, 
        // but we can check state.
    }

    /** @test */
    public function it_increments_coupon_usage_on_order()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['price' => 200000]); // > free shipping threshold for Bogota
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'TESTUSAGE',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'active' => true,
            'usage_count' => 0
        ]);

        Livewire::test(CheckoutPage::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'john@example.com')
            ->set('phone', '1234567890')
            ->set('document_number', '1234567890')
            ->set('delivery_date', '2026-01-20')
            ->set('city', 'Bogotá')
            ->set('street_address', 'Calle 123')
            ->set('payment_method', 'COD')
            ->set('coupon_code_input', 'TESTUSAGE')
            ->call('applyCoupon')
            ->call('placeOrder');

        $this->assertDatabaseHas('orders', [
            'coupon_code' => 'TESTUSAGE',
            'discount_amount' => 10000,
            'grand_total' => 190000 // 200,000 - 10,000 + 0 (free shipping for > 200k in bogota)
            // Wait, free shipping threshold is 200k. 
            // In CheckoutPage: $free_shipping_if = CartManagement::FREE_SHIPPING_THRESHOLD; (200000)
            // Logic in getShippingCost: if ($subtotal >= self::FREE_SHIPPING_THRESHOLD) return 0;
            // $subtotal is 200000. So shipping is 0.
            // Grand Total = 200000 - 10000 + 0 = 190000.
        ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'TESTUSAGE',
            'usage_count' => 1
        ]);
    }
}
