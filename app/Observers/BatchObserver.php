<?php
namespace App\Observers;

use App\Models\Batch;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class BatchObserver
{
    /**
     * Contexto: Antes de insertar en la BD (Before Insert)
     */
    public function creating(Batch $batch): void
    {
        // 1. Asignación de usuario
        if (auth()->check()) {
            $batch->user_id = auth()->id();
        }

        // 2. Lógica de Código Inteligente
        if (!$batch->code) {
            $this->generateBatchCode($batch);
        }
    }

    /**
     * Contexto: Después de insertar en la BD (After Insert)
     * Ideal para descontar inventario ya que necesitamos el ID del Batch
     */
    public function created(Batch $batch): void
    {
        if ($batch->recipe_id) {
            $this->deductInventory($batch);
        }
    }

    /**
     * Contexto: Antes de actualizar (Before Update)
     */
    public function updating(Batch $batch): void
    {
        if ($batch->isDirty('quantity') && (int)$batch->quantity === 0) {
            $batch->status = 'finalized';
            
            $now = now()->format('Y-m-d H:i');
            $user_name = auth()->user()->name ?? 'Sistema';
            
            if (!str_contains($batch->observations, 'LOTE FINALIZADO')) {
                $batch->observations .= "\n- [{$now}] {$user_name}: El lote ha llegado a 0 unidades y se ha finalizado automáticamente.";
            }
        }
    }

    /**
     * Métodos privados de soporte para mantener limpio el Observer
     */
    private function generateBatchCode(Batch $batch): void
    {
        if ($batch->parent_batch_id) {
            $parent = Batch::find($batch->parent_batch_id);
            $childCount = Batch::where('parent_batch_id', $batch->parent_batch_id)->count() + 1;
            $batch->code = $parent->code . '-F' . $childCount;
        } else {
            $prefix = strtoupper(substr($batch->strain?->name ?? 'BT', 0, 3));
            $batch->code = "{$prefix}-" . now()->format('ymd') . "-" . rand(10, 99);
        }
    }

    private function deductInventory(Batch $batch): void
    {
        
        // Cargamos la relación asegurando que traiga los campos de la tabla pivote
        $batch->loadMissing(['recipe.supplies' => function ($query) {
            $query->withPivot('calculation_mode', 'value');
        }]);
        
        $recipe = $batch->recipe;
        if (!$recipe) return;
        
        Log::info('Datos para cálculo:', [
            'peso_seco' => $batch->weigth_dry, // Verifica si es weigth o weight
            'cantidad_lote' => $batch->quantity,
            'insumos_count' => $recipe->supplies->count()
        ]);
        foreach ($recipe->supplies as $supply) {
            // Accedemos explícitamente a los datos del pivote
            $mode = $supply->pivot->calculation_mode;
            $value = $supply->pivot->value;

            // Log de depuración interna para ver si los valores del pivote existen
            Log::info("Procesando insumo: {$supply->name}", [
                'modo' => $mode,
                'valor_receta' => $value
            ]);

            $amountToDeduct = ($mode === 'percentage') 
                ? ($batch->weigth_dry * $value) / 100 
                : $value * $batch->quantity;

            if ($amountToDeduct > 0) {
                $supply->decrement('quantity', $amountToDeduct);
                
                // Usamos el nombre de la columna real del lote para la bitácora
                $batch->observations .= "\n- [Insumo] {$supply->name}: {$amountToDeduct} descontados.";
            }
        }

        // 2. LÓGICA DINÁMICA DE BOLSAS (Basado en Peso por Unidad)
        $bagName = null;
        $unitWeight = $batch->bag_weight; // Asumiendo que este es el campo "Peso por Unidad"

        if ($unitWeight > 2 && $unitWeight < 3) {
            $bagName = 'Bolsa pequeña'; // O el nombre exacto en tu inventario
        } elseif ($unitWeight >= 3.5 && $unitWeight <= 4.5) {
            $bagName = 'Bolsa grande';
        }

        if ($bagName) {
            $bag = \App\Models\Supply::where('name', 'LIKE', "%{$bagName}%")->first();

            if ($bag && $bag->quantity >= $batch->quantity) {
                $bag->decrement('quantity', $batch->quantity);
                $batch->observations .= "\n- [Empaque] {$bag->name}: {$batch->quantity} unidades descontadas.";
            } else {
                // Si no existe el insumo o no hay stock suficiente
                $batch->observations .= "\n- ⚠️ ADVERTENCIA: No se pudo descontar empaque ({$bagName}). Stock insuficiente o insumo no encontrado.";
                
                // Opcional: Notificación visual en Filament (si se guarda en sesión)
                Notification::make()
                    ->title('Stock de bolsas insuficiente')
                    ->body("No se encontró stock para: {$bagName}")
                    ->warning()
                    ->send();
            }
        }
        
        $batch->saveQuietly();
    }
}