<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Models\Product;
use App\Livewire\CheckoutPage;
use Livewire\Livewire;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_place_an_order_and_it_generates_a_reference()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 15000, 'stock' => 10]);

        // 1. Datos del carrito para la cookie
        $cartData = [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'unit_amount' => 15000,
                'total_amount' => 15000,
            ]
        ];
        // 1. SIMULAR LA SESIÓN (Lo que hizo CartPage)
        // Ajusta las llaves 'shipping_amount' y 'grand_total' según las uses en tu código
        session([
            'checkout_shipping' => [
                'is_bogota'     => true,
                'cost'          => 15000,
                'delivery_date' => now()->addDay(6)
            ]
        ]);


       // 3. Ejecutar Test inyectando la cookie 'cart_items'
        Livewire::withCookie('cart_items', json_encode($cartData))
            ->actingAs($user)
            ->test(CheckoutPage::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('city', 'Bogotá')
            ->set('document_number', '12345678')
            ->set('document_type', 'CC')
            ->set('payment_method', 'BOLD')
            ->set('phone', '3001234567')
            ->set('street_address', 'Calle 123')
            ->set('state', 'Cundinamarca')
            ->set('zip_code', '110111')
            ->set('email', $user->email) // Necesario para Bold
            ->call('placeOrder')
            ->assertHasNoErrors();

        // ASSERT: La orden debe existir en la base de datos
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'grand_total' => 30000,
            'shipping_amount' => 15000,
        ]);

        // ASSERT: Verificar que la referencia NO sea nula
        $order = Order::where('user_id', $user->id)->first();
        $this->assertNotNull($order->reference, "La orden se creó sin una referencia.");
        $this->assertStringStartsWith('ORD-', $order->reference);
    }
}