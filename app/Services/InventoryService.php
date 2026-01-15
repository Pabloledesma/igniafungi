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

    /**
     * returns a Batch that is in incubation phase for the product's strain.
     */
    public function getPreorderBatch(Product $product): ?Batch
    {
        if (!$product->strain_id) {
            return null;
        }

        // Find batch in incubation phase (using phases pivot)
        return Batch::where('strain_id', $product->strain_id)
            ->whereHas('phases', function ($query) {
                // Check if current active phase is 'Incubation'
                // Depending on seeding 'incubation' slug usually exists.
                // Or we can check name 'Incubación' or slug 'incubation'.
                $query->where('slug', 'incubation')
                    ->whereNull('batch_phases.finished_at');
            })
            ->where('expected_yield', '>', 0)
            ->orderBy('estimated_harvest_date', 'asc')
            ->first();
    }

    /**
     * Checks if there is enough expected yield to cover current reservations + new quantity
     */
    public function validatePreorderStock(Product $product, int $quantity): bool
    {
        $batch = $this->getPreorderBatch($product);
        if (!$batch) {
            return false;
        }

        // Calculate total weight requested (Quantity * Product Weight)
        // Ensure product weight is set (default to 500g if 0 or null to avoid division by zero or free pass, but actually 0 would mean 0 consumption)
        $weightPerUnit = $product->weight > 0 ? $product->weight : 500;
        $requestedWeight = $quantity * $weightPerUnit;

        // Calculate already reserved weight
        // Sum of OrderItems (is_preorder=true) linked to this batch ?? Or linked to this strain?
        // Since order_item might not be linked to batch ID directly at creation (unless we do it),
        // we should probably query OrderItems for this Product that are preorders.
        // PROMPT: "validar kilos ... que el lote tiene proyectados"

        // Simplification: Count all Active Preorders for this Product and compare against the Batch's remaining capacity.
        // Ideally we associate the Preorder to the Batch.
        // For now, let's sum all active preorder items for this product.

        $reservedUnits = \App\Models\OrderItem::where('product_id', $product->id)
            ->where('is_preorder', true)
            ->whereHas('order', function ($query) {
                // User requirement: Only "Paid" orders count against capacity.
                // We include subsequent statuses that imply payment (processing, shipping, delivered, completed).
                // "new", "pending", "cancelled" are excluded.
                $query->whereIn('status', ['paid', 'processing', 'shipping', 'delivered', 'completed']);
            })
            ->sum('quantity');

        $totalReservedWeight = $reservedUnits * $weightPerUnit;

        return ($requestedWeight + $totalReservedWeight) <= $batch->expected_yield;
    }
}