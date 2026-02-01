<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CheckoutPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Delivery;
use App\Helpers\CartManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos un usuario y lo autenticamos para todas las pruebas
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function checkout_page_renders_correctly()
    {
        Livewire::test(CheckoutPage::class)
            ->assertStatus(200)
            ->assertSee('Información del cliente');
    }

    /** @test */
    public function it_validates_all_required_fields_and_formats()
    {
        $product = Product::factory()->create(['price' => 20000, 'stock' => 1]);
        $this->seedCart($product);

        Livewire::test(CheckoutPage::class)
            // 1. Probar campos vacíos
            ->set('first_name', '')
            ->set('last_name', '')
            ->set('email', '')
            ->set('phone', '')
            ->set('document_number', '')
            ->set('payment_method', '')
            ->set('city', '')
            ->set('delivery_date', '')
            ->call('placeOrder')
            ->assertHasErrors([
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required',
                'phone' => 'required',
                'document_number' => 'required',
                'payment_method' => 'required',
                'city' => 'required',
            ])
            // 2. Probar formatos incorrectos
            ->set('email', 'correo-no-valido')
            ->set('document_number', 'letras-en-documento')
            ->call('placeOrder')
            ->assertHasErrors([
                'email' => 'email',
                'document_number' => 'numeric',
            ])
            // 3. Probar longitud mínima
            ->set('first_name', 'Ab')
            ->call('placeOrder')
            ->assertHasErrors(['first_name' => 'min']);
    }

    /** @test */
    public function place_order_without_products()
    {

        // Asegurarnos de que el carrito esté vacío explícitamente
        CartManagement::clearCartItems();

        Livewire::test(CheckoutPage::class)
            // 1. Probar campos vacíos
            ->set('first_name', 'sdfgsdg')
            ->set('last_name', 'sdfgsdg')
            ->set('email', 'test@test')
            ->set('phone', '23454325')
            ->set('document_number', '2345325')
            ->set('payment_method', 'BOLD')
            ->set('city', 'Bogotá')
            ->set('delivery_date', '01/01/2023')
            ->call('placeOrder')
            ->assertHasErrors(['cart']);
    }

    /** @test */
    public function it_calculates_shipping_cost_for_bogota_correcty()
    {
        // 1. Crear el producto en la base de datos de prueba
        $product = \App\Models\Product::factory()->create(['price' => 30000, 'stock' => 1]);

        $this->seedCart($product, true, 'Suba', 11000);


        // 5. Ejecutar el test de Livewire
        Livewire::test(CheckoutPage::class)
            // Forzamos el set de city y location para disparar calculateShipping()
            ->set('city', 'Bogotá')
            ->set('location', 'Suba')
            ->assertSet('shipping_cost', 10000)
            ->assertSet('grand_total', 40000);
    }

    /** @test */
    public function it_creates_an_order_and_a_delivery_for_cod_method()
    {
        $product = Product::factory()->create(['price' => 50000, 'stock' => 2]);
        $this->seedCart($product, true, 'Engativa', 9000);

        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        Livewire::test(CheckoutPage::class)
            ->set('first_name', 'Pablo')
            ->set('last_name', 'Ignia')
            ->set('email', 'pablo@igniafungi.com')
            ->set('phone', '3001234567')
            ->set('document_type', 'CC')
            ->set('document_number', '12345678')
            ->set('city', 'Bogotá')
            ->set('location', 'Engativa')
            ->set('street_address', 'Calle 80 #10-20')
            ->set('payment_method', 'COD')
            ->set('delivery_date', $deliveryDate)
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect(); // Verifica que redirija a la página de éxito

        // 1. Verificar que la orden existe en la base de datos
        $this->assertDatabaseHas('orders', [
            'payment_method' => 'COD',
            'grand_total' => 110000, // 50k + 10k de Engativá
        ]);

        // 2. Verificar que se creó el objeto Delivery vinculado
        $order = Order::first();
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'scheduled_at' => $deliveryDate,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_creates_an_order_with_free_delivery()
    {
        $freeShippingTreshold = CartManagement::FREE_SHIPPING_THRESHOLD;
        $product = Product::factory()->create(['price' => 50000, 'stock' => 5]);
        $this->seedCart($product, true, 'Engativa', 9000);

        $deliveryDate = now()->addDays(2)->format('Y-m-d');

        Livewire::test(CheckoutPage::class)
            ->set('first_name', 'Pablo')
            ->set('last_name', 'Ignia')
            ->set('email', 'pablo@igniafungi.com')
            ->set('phone', '3001234567')
            ->set('document_type', 'CC')
            ->set('document_number', '12345678')
            ->set('city', 'Bogotá')
            ->set('location', 'Engativa')
            ->set('street_address', 'Calle 80 #10-20')
            ->set('payment_method', 'COD')
            ->set('delivery_date', $deliveryDate)
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect(); // Verifica que redirija a la página de éxito

        // 1. Verificar que la orden existe en la base de datos
        $this->assertDatabaseHas('orders', [
            'payment_method' => 'COD',
            'grand_total' => 250000, // 50k + 9k de Engativá
        ]);

        // 2. Verificar que se creó el objeto Delivery vinculado
        $order = Order::first();
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'scheduled_at' => $deliveryDate,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function it_emits_bold_event_when_payment_method_is_bold()
    {
        $product = Product::factory()->create(['price' => 20000, 'stock' => 1]);
        $this->seedCart($product);

        Livewire::test(CheckoutPage::class)
            ->set('first_name', 'Socia')
            ->set('last_name', 'Ignia')
            ->set('email', 'socia@igniafungi.com')
            ->set('phone', '3119876543')
            ->set('document_type', 'CC')
            ->set('document_number', '87654321')
            ->set('city', 'Bogotá')
            ->set('location', 'Chapinero')
            ->set('street_address', 'Carrera 7 #40-00')
            ->set('payment_method', 'BOLD')
            ->set('delivery_date', now()->addDay()->format('Y-m-d'))
            ->call('placeOrder')
            ->assertDispatched('open-bold-checkout'); // Verifica que se dispare el JS de Bold
    }

    /** @test */
    public function it_redirects_to_confirmation_without_loading_screen_for_cod()
    {
        $product = Product::factory()->create(['price' => 50000, 'stock' => 1]);
        $this->seedCart($product);

        $test = Livewire::test(CheckoutPage::class)
            ->set('first_name', 'Socia')
            ->set('last_name', 'Ignia')
            ->set('email', 'socia@igniafungi.com')
            ->set('phone', '3119876543')
            ->set('document_type', 'CC')
            ->set('document_number', '87654321')
            ->set('city', 'Bogotá')
            ->set('location', 'Chapinero')
            ->set('street_address', 'Carrera 7 #40-00')
            ->set('payment_method', 'COD')
            ->set('delivery_date', now()->addDay()->format('Y-m-d'))
            ->call('placeOrder');


        // Obtenemos la última orden creada para saber su referencia exacta
        $order = Order::latest()->first();

        // Verificamos la redirección con los parámetros exactos
        $test->assertRedirect(route('order.thanks', [
            'reference' => $order->reference,
            'payment' => 'cod'
        ]));

        $this->get(route('order.thanks', [
            'reference' => $order->reference,
            'payment' => 'cod'
        ]))
            ->assertStatus(200)
            ->assertSee('Pedido Recibido')
            ->assertSee('Pagarás en efectivo al recibir tus productos.');
    }

    // Método auxiliar para llenar el carrito en la sesión/cookie de prueba
    private function seedCart($product, $is_bogota = true, $location = null, $shippingCost = 11000)
    {
        $cartItems = [
            [
                'product_id' => $product->id,
                // 'name' no suele estar en order_items, se usa para el carrito visual
                'name' => $product->name,
                'unit_amount' => $product->price,
                'quantity' => $product->stock, // Asegúrate de que esta llave sea 'quantity'
                'total_amount' => $product->price * $product->stock,
            ]
        ];

        \Illuminate\Support\Facades\Cookie::queue('cart_items', json_encode($cartItems), 60);

        session([
            'checkout_shipping' => [
                'is_bogota' => $is_bogota,
                'location' => $location,
                'cost' => $shippingCost,
            ]
        ]);
    }
}