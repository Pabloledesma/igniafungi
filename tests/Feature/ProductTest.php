<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Strain;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Resources\Products\Pages\ListProducts;

class ProductTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_finds_and_updates_the_corresponding_dehydrated_product_automatically()
    {
        // 1. Preparar Categorías
        $freshCat = Category::factory()->create(['name' => 'Frescos']);
        $dryCat = Category::factory()->create(['name' => 'Deshidratados']);

        $strain = Strain::factory()->create(['name' => 'Eryngii']);

        // 2. Crear los dos productos vinculados por la misma Strain
        $freshProduct = Product::factory()->create([
            'name' => 'Eryngii Fresco',
            'strain_id' => $strain->id,
            'category_id' => $freshCat->id,
            'stock' => 10.0
        ]);

        $driedProduct = Product::factory()->create([
            'name' => 'Eryngii Deshidratado',
            'strain_id' => $strain->id,
            'category_id' => $dryCat->id,
            'stock' => 1.0 // Stock inicial
        ]);

        // 3. Actuar: Transformamos 5kg de fresco y obtenemos 0.5kg de seco
        \App\Services\InventoryService::processDehydration(
            sourceProduct: $freshProduct,
            quantityRemoved: 5.0,
            quantityAdded: 0.5
        );

        // 4. Verificar
        $this->assertEquals(5.0, $freshProduct->fresh()->stock);
        $this->assertEquals(1.5, $driedProduct->fresh()->stock); // 1.0 inicial + 0.5 nuevo
    }

    /** @test */
    public function it_can_process_dehydration_from_filament_table()
    {
        // 1. Setup exacto (Asegúrate de que el nombre contenga 'Fresco' para que sea visible)
        $freshCat = Category::factory()->create(['name' => 'Hongos Frescos']);
        $dryCat = Category::factory()->create(['name' => 'Deshidratados']);
        $strain = Strain::factory()->create(['name' => 'Orellana']);

        $freshProduct = Product::factory()->create([
            'strain_id' => $strain->id,
            'category_id' => $freshCat->id,
            'stock' => 10
        ]);

        $dryProduct = Product::factory()->create([
            'strain_id' => $strain->id,
            'category_id' => $dryCat->id,
            'stock' => 0
        ]);

        // 2. Testeo con aserciones de paso a paso
        \Livewire\Livewire::test(ListProducts::class)
            // Verificamos que sea visible (si esto falla, aquí está el problema)
            ->assertTableActionVisible('deshidratar', $freshProduct)
            ->mountTableAction('deshidratar', $freshProduct)
            ->setTableActionData([
                'cantidad_fresco' => 5,
                'cantidad_seco' => 0.5,
            ])
            // IMPORTANTE: En v3 usamos callMountedTableAction para ejecutar lo que está en el modal
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        // 3. Verificación
        $this->assertEquals(5, $freshProduct->fresh()->stock);
        $this->assertEquals(0.5, $dryProduct->fresh()->stock);
    }

    /** @test */
    public function it_can_dehydrate_products_across_different_primary_categories()
    {
        // 1. Preparar Categorías
        $medicinaCat = Category::factory()->create(['name' => 'Medicina Ancestral', 'slug' => 'medicina-ancestral']);
        $dryCat = Category::factory()->create(['name' => 'Deshidratados', 'slug' => 'deshidratados-3']);
        $strain = Strain::factory()->create(['name' => 'Melena de León']);

        // 2. Crear los productos (Vinculados por la Strain)
        $fresco = Product::factory()->create([
            'name' => 'Melena de León Fresca',
            'strain_id' => $strain->id,
            'category_id' => $medicinaCat->id, // Categoría principal
            'stock' => 10
        ]);

        $seco = Product::factory()->create([
            'name' => 'Melena de León Deshidratada',
            'strain_id' => $strain->id,
            'category_id' => $dryCat->id, // Categoría Deshidratados
            'stock' => 0
        ]);

        // 3. Actuar: Deshidratar
        \App\Services\InventoryService::processDehydration($fresco, 5, 0.5);

        // 4. Verificar
        $this->assertEquals(5, $fresco->fresh()->stock);
        $this->assertEquals(0.5, $seco->fresh()->stock);
    }
}
