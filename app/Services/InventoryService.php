<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    // app/Services/InventoryService.php

   // app/Services/InventoryService.php

    public static function processDehydration(Product $sourceProduct, float $quantityRemoved, float $quantityAdded = null)
    {
        return DB::transaction(function () use ($sourceProduct, $quantityRemoved, $quantityAdded) {
            // 1. Validar stock
            if ($sourceProduct->stock < $quantityRemoved) {
                throw new \Exception("Stock insuficiente de {$sourceProduct->name}.");
            }

            // 2. Buscar el producto deshidratado hermano
            $targetProduct = Product::where('strain_id', $sourceProduct->strain_id)
                ->whereHas('category', function ($query) {
                    $query->where('name', 'like', '%Deshidratado%');
                })
                ->first();

            if (!$targetProduct) {
                throw new \Exception("No existe un producto deshidratado para la cepa: {$sourceProduct->strain->name}");
            }

            // 3. CÁLCULO AUTOMÁTICO
            // Asumimos un 10% de rendimiento (puedes ajustar este factor o traerlo de la cepa)
            $dehydrationRatio = 0.1;
             
            if (!$quantityAdded) {
                $quantityAdded = $quantityRemoved * $dehydrationRatio;
            } else {
                // Si el valor es manual, calculamos el ratio real para el reporte
                $dehydrationRatio = $quantityAdded / $quantityRemoved;
            }

            // 4. Movimiento de Inventario
            $sourceProduct->decrement('stock', $quantityRemoved);
            $targetProduct->increment('stock', $quantityAdded);

            return [
                'fresh_removed' => $quantityRemoved,
                'dried_added' => $quantityAdded,
                'ratio_applied' => ($dehydrationRatio * 100) . '%'
            ];
        });
    }
}