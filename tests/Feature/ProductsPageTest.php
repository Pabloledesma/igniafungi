<?php
namespace Tests\Feature\Livewire;

use App\Livewire\ProductsPage;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductsPageTest extends TestCase
{
    use RefreshDatabase; // Limpia la base de datos en cada prueba

    /** @test */
    public function it_renders_successfully()
    {
        Livewire::test(ProductsPage::class)
            ->assertStatus(200);
    }

    /** @test */
    public function it_renders_correctly_even_if_products_have_no_images()
    {
        // Creamos un producto con el campo images nulo o vacío
        $productWithoutImages = Product::factory()->create([
            'is_active' => true,
            'images' => null // O [] dependiendo de tu migración
        ]);

        Livewire::test(ProductsPage::class)
            ->assertStatus(200)
            ->assertSee($productWithoutImages->name)
            // Verificamos que se cargue la imagen por defecto o que no haya error
            ->assertSee('Imagen-interrogante-2.png'); 
    }

}