<?php
namespace App\Observers;

use App\Models\Batch;
use App\Models\Phase;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class BatchObserver
{
    protected static $processing = null;
    // Propiedad para evitar duplicidad
    protected static array $processedBatches = [];

    public static function clearProcessed(): void
    {
        self::$processedBatches = [];
    }
    
    /**
     * Contexto: Antes de insertar en la BD (Before Insert)
     */
    public function creating(Batch $batch): void
    {
        if (auth()->check()) {
            $batch->user_id = auth()->id();
        }

        if (!$batch->code) {
            $this->generateBatchCode($batch);
        }

        // Aseguramos que el status inicial sea preparación si no viene uno definido
        if (!$batch->status) {
            $batch->status = 'preparation';
        }
    }

    /**
     * Contexto: Después de insertar en la BD (After Insert)
     * Ideal para descontar inventario ya que necesitamos el ID del Batch
     */
    public function created(Batch $batch): void
    {
        // Si el ID ya está en el array, no procesamos de nuevo
        if (in_array($batch->id, self::$processedBatches)) {
            return;
        }

        // Registrar el ID como procesado
        self::$processedBatches[] = $batch->id;

        $this->assignInitialPhase($batch);

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
     * Lógica para el Kanban: Evita lotes huérfanos
     */
    private function assignInitialPhase(Batch $batch): void
    {
        $firstPhase = Phase::where('slug', 'preparation')->first();

        if ($firstPhase) {
            $batch->phases()->attach($firstPhase->id, [
                'user_id' => $batch->user_id ?? (auth()->id() ?? 1),
                'started_at' => now(),
            ]);
            
            Log::info("Lote {$batch->code} asignado a fase: {$firstPhase->name}");
        } else {
            Log::error("No se encontró la fase 'preparation'. ¿Ejecutaste el PhaseSeeder?");
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
           // Manejo de strain_id nulo para la fase de preparación
            $prefix = 'SUB'; // Default para Sustrato/Substratum
            
            if ($batch->strain_id) {
                $strainName = \App\Models\Strain::where('id', $batch->strain_id)->value('name');
                if ($strainName) {
                    $prefix = strtoupper(substr($strainName, 0, 3));
                }
            }
            
            $batch->code = "{$prefix}-" . now()->format('ymd') . "-" . str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        }
    }

    private function deductInventory(Batch $batch): void
    {
        
        // Forzamos la carga de la receta si no está presente
        $recipe = $batch->recipe()->with('supplies')->first();
        
        if (!$recipe || $recipe->supplies->isEmpty()) {
            Log::warning("El lote {$batch->id} no tiene receta o insumos vinculados.");
            return;
        }

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
                // USAR decrement() directamente en la base de datos
                // Esto es más seguro y evita problemas de concurrencia
                $supply->decrement('quantity', $amountToDeduct);
                
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