<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Batch;
use App\Models\Strain;
use App\Models\Harvest;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HarvestTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function harvest_does_not_increase_stock_for_products_in_dehydrated_category()
    {
        // 1. Crear categorías
        $freshCategory = Category::factory()->create(['name' => 'Frescos']);
        $dehydratedCategory = Category::factory()->create(['name' => 'Deshidratados']);
        
        $strain = Strain::factory()->create(['name' => 'Eryngii']);

        // 2. Producto Fresco (Debe aumentar)
        $freshProduct = Product::factory()->create([
            'name' => 'Eryngii Fresco',
            'strain_id' => $strain->id,
            'category_id' => $freshCategory->id,
            'stock' => 0
        ]);

        // 3. Producto Deshidratado (NO debe aumentar)
        $driedProduct = Product::factory()->create([
            'name' => 'Eryngii Deshidratado',
            'strain_id' => $strain->id,
            'category_id' => $dehydratedCategory->id,
            'stock' => 0
        ]);

        $batch = Batch::factory()->create(['strain_id' => $strain->id]);

        // 4. Actuar: Registramos cosecha de 3kg
        Harvest::create([
            'batch_id' => $batch->id,
            'weight' => 3.0,
            'harvest_date' => now(),
            'user_id' => 1
        ]);

        // 5. Verificar
        $this->assertEquals(3.0, $freshProduct->fresh()->stock, "El hongo fresco debería haber subido a 3kg");
        $this->assertEquals(0, $driedProduct->fresh()->stock, "El hongo deshidratado debería seguir en 0kg");
    }
}
