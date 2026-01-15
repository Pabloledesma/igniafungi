<?php

namespace Tests\Feature;

use App\Helpers\CartManagement;
use App\Livewire\CheckoutPage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneCouponPerCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CartManagement::clearCartItems();
    }

    /** @test */
    public function user_cannot_apply_coupon_if_prior_order_has_coupon()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Created historical order with coupon
        Order::create([
            'user_id' => $user->id,
            'grand_total' => 50000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'shipping_amount' => 0,
            'coupon_code' => 'OLDCODE',
            'discount_amount' => 5000,
        ]);

        $product = Product::factory()->create(['price' => 50000]);
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'NEWCODE',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'active' => true,
        ]);

        Livewire::test(CheckoutPage::class)
            ->set('coupon_code_input', 'NEWCODE')
            ->call('applyCoupon')
            ->assertSet('applied_coupon_code', null)
            ->assertSeeHtml('Lo sentimos, ya has redimido un código promocional anteriormente');
    }

    /** @test */
    public function user_can_apply_coupon_if_prior_order_has_no_coupon()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Historical order WITHOUT coupon
        Order::create([
            'user_id' => $user->id,
            'grand_total' => 50000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'shipping_amount' => 0,
            'coupon_code' => null,
            'discount_amount' => 0,
        ]);

        $product = Product::factory()->create(['price' => 50000]);
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'NEWCODE',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'active' => true,
        ]);

        Livewire::test(CheckoutPage::class)
            ->set('coupon_code_input', 'NEWCODE')
            ->call('applyCoupon')
            ->assertSet('applied_coupon_code', 'NEWCODE');
    }

    /** @test */
    public function place_order_fails_if_policy_violated_at_last_moment()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create(['price' => 50000]);
        CartManagement::addItemsToCart($product->id, 1);

        $coupon = Coupon::create([
            'code' => 'SNEAKYCODE',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'active' => true,
        ]);

        // Scenario: User opens checkout, applies coupon. Then in another tab completes an order with a coupon.
        // Then comes back to this tab and clicks placeOrder for a NEW order with a coupon.

        // 1. Setup the component state as if coupon is applied
        $component = Livewire::test(CheckoutPage::class)
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'test@example.com')
            ->set('phone', '123')
            ->set('document_number', '123')
            ->set('document_type', 'CC')
            ->set('city', 'Bogotá')
            ->set('street_address', 'Calle Falsa 123')
            ->set('payment_method', 'cod')
            ->set('coupon_code_input', 'SNEAKYCODE')
            ->call('applyCoupon')
            ->assertSet('applied_coupon_code', 'SNEAKYCODE');

        // 2. BACKDOOR: Insert a conflicting order in DB now
        Order::create([
            'user_id' => $user->id,
            'grand_total' => 50000,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'coupon_code' => 'OTHERCODE', // Prior redemption
            'discount_amount' => 5000,
        ]);

        // 3. Try to place order. Should fail or throw validation exception
        // We expect it to NOT create the order.
        $component->call('placeOrder');

        // Assert no new order created with SNEAKYCODE
        $this->assertDatabaseMissing('orders', [
            'coupon_code' => 'SNEAKYCODE'
        ]);

        // Ideally we should see an error message
        $component->assertSeeHtml('Lo sentimos, ya has redimido un código promocional anteriormente');
    }
}
