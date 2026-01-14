<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\Strain;

class InventoryService
{
    // app/Services/InventoryService.php

    // app/Services/InventoryService.php

    /**
     * Get total available stock (in units/yield) for a specific Strain across all active batches.
     */
    public function getAvailableStock(int $strainId): int
    {
        $batches = Batch::where('strain_id', $strainId)
            ->whereIn('status', ['incubation', 'fruiting'])
            ->get();

        $totalAvailable = 0;

        foreach ($batches as $batch) {
            $totalAvailable += $this->getBatchAvailableStock($batch);
        }

        return $totalAvailable;
    }

    /**
     * Get available stock for a specific Batch.
     * Logic: Estimated Yield (Capacity) - Pre-sold Quantity.
     */
    public function getBatchAvailableStock(Batch $batch): int
    {
        // Using 'quantity' (bags/units) as the limit for now.
        $capacity = $batch->quantity;
        $sold = $batch->pre_sold_quantity;

        return max(0, $capacity - $sold);
    }

    /**
     * Get the earliest estimated harvest date for a specific Strain among active batches.
     */
    public function getNextHarvestDate(int $strainId): ?string
    {
        // Find the batch closest to harvest
        $batches = Batch::where('strain_id', $strainId)
            ->whereIn('status', ['incubation', 'fruiting'])
            ->get();

        $earliestDate = null;

        foreach ($batches as $batch) {
            $currentPhase = $batch->phases()->whereNull('finished_at')->first();
            if (!$currentPhase)
                continue;

            $days = $batch->strain->incubation_days ?? 15;
            if ($currentPhase->name === 'Fructificación') {
                $days = 7; // Shorter time if already fruiting
            }

            if ($currentPhase->pivot && $currentPhase->pivot->started_at) {
                $batchDate = \Carbon\Carbon::parse($currentPhase->pivot->started_at)->addDays($days);

                if ($earliestDate === null || $batchDate->lt($earliestDate)) {
                    $earliestDate = $batchDate;
                }
            }
        }

        return $earliestDate ? $earliestDate->format('Y-m-d') : null;
    }

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