<?php

namespace Tests\Feature;

use App\Helpers\CartManagement;
use App\Models\Product;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartManagementTest extends TestCase
{
    use RefreshDatabase;

  /** @test */
    public function it_can_add_a_new_product_to_the_cart()
    {
        $product = Product::factory()->create(['name' => 'Test', 'price' => 100]);

        // Ejecutamos la lógica
        CartManagement::addItemsToCart($product->id);

        // Verificamos que la cookie esté en la cola de salida
        $this->assertTrue(Cookie::hasQueued('cart_items'));
        
        $cookie = Cookie::queued('cart_items');
        $data = json_decode($cookie->getValue(), true);

        $this->assertCount(1, $data);
        $this->assertEquals(100, $data[0]['total_amount']);
    }

    /** @test */
    public function it_increments_quantity_if_product_already_in_cart()
    {
        $product = Product::factory()->create(['price' => 50]);

        // Agregamos el mismo producto dos veces
        CartManagement::addItemsToCart($product->id);
        $count = CartManagement::addItemsToCart($product->id);

        $itemsInCookie = CartManagement::getCartItemsFromCookie();

        $this->assertEquals(1, $count); // El conteo de registros sigue siendo 1
        $this->assertEquals(2, $itemsInCookie[0]['quantity']);
        $this->assertEquals(100, $itemsInCookie[0]['total_amount']);
    }

    /** @test */
    public function it_returns_empty_array_if_cookie_is_empty()
    {
        Cookie::expire('cart_items');
        
        $items = CartManagement::getCartItemsFromCookie();

        $this->assertIsArray($items);
        $this->assertEmpty($items);
    }

    /** @test */
    public function it_can_clear_all_items_from_cart()
    {
        $product = Product::factory()->create();
        CartManagement::addItemsToCart($product->id);

        CartManagement::clearCartItems();
        
        // Simulamos que la cookie expiró para el siguiente request
        $items = CartManagement::getCartItemsFromCookie(); 
        $this->assertEmpty($items);
    }

    /** @test */
    public function it_can_decrement_quantity_to_cart_item()
    {
        $product = Product::factory()->create(['price' => 100]);
        
        // 1. Agregamos el producto dos veces (Cantidad: 2)
        CartManagement::addItemsToCart($product->id);
        CartManagement::addItemsToCart($product->id);

        // 2. Ejecutamos decremento
        $items = CartManagement::decrementQuantityToCartItem($product->id);

        // 3. Afirmar
        $this->assertEquals(1, $items[0]['quantity'], "La cantidad debería ser 1");
        $this->assertEquals(100, $items[0]['total_amount'], "El total debería ser 100");
    }

    /** @test */
    public function it_does_not_decrement_quantity_below_one()
    {
        $product = Product::factory()->create(['price' => 100]);
        CartManagement::addItemsToCart($product->id); // Cantidad: 1

        // Intentamos decrementar estando en 1
        $items = CartManagement::decrementQuantityToCartItem($product->id);

        $this->assertEquals(1, $items[0]['quantity'], "La cantidad debería ser 1");
        $this->assertEquals(100, $items[0]['total_amount'], "El total debería ser 100");
    }

    /** @test */
    public function it_calculates_the_grand_total_correctly()
    {
        $product1 = Product::factory()->create(['price' => 150]);
        $product2 = Product::factory()->create(['price' => 200]);

        CartManagement::addItemsToCart($product1->id);
        CartManagement::addItemsToCart($product2->id);
        
        $items = CartManagement::getCartItemsFromCookie();

        // 4. Calcular total
        $grandTotal = CartManagement::calculateGrandTotal($items);

        $this->assertEquals(350, $grandTotal, "El total debería ser 350");
    }
}