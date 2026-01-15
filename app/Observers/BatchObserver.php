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
    public static $isSyncingLoss = false;

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
            $batch->code = $this->generateBatchCode($batch);
        }


        // Aseguramos que el status inicial sea preparación si no viene uno definido
        if (!$batch->status) {
            $batch->status = 'active';
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

        // Calculate Estimated Harvest Date if created with inoculation_date (e.g. Seeder)
        if ($batch->strain_id && $batch->inoculation_date) {
            $this->setEstimatedHarvestDate($batch);
            $batch->saveQuietly(); // Persist the calculated date without triggering loop
        }
    }

    /**
     * Contexto: Antes de actualizar (Before Update)
     */
    public function updating(Batch $batch): void
    {
        if ($batch->isDirty('strain_id') && $batch->strain_id !== null) {
            $batch->code = $this->generateBatchCode($batch, keepNumber: true);

            Log::info("Prefijo de lote actualizado: {$batch->code}");
        }

        // Logic: Calculate Estimated Harvest Date on Inoculation Change
        if ($batch->isDirty('inoculation_date') && $batch->inoculation_date) {
            $this->setEstimatedHarvestDate($batch);
        }

        if ($batch->isDirty('quantity') && (int) $batch->quantity === 0) {
            $batch->status = 'finalized';

            $now = now()->format('Y-m-d H:i');
            $user_name = auth()->user()->name ?? 'Sistema';

            if (!str_contains($batch->observations, 'LOTE FINALIZADO')) {
                $batch->observations .= "\n- [{$now}] {$user_name}: El lote ha llegado a 0 unidades y se ha finalizado automáticamente.";
            }
        }

        // --- CONTAMINATION SYNC (Batch -> BatchLoss) ---
        if (!self::$isSyncingLoss && $batch->isDirty('contaminated_quantity')) {
            $diff = $batch->contaminated_quantity - $batch->getOriginal('contaminated_quantity');

            if ($diff > 0) {
                // Prevenir bucle: LossObserver no debe actualizar el batch de nuevo
                \App\Observers\LossObserver::$shouldUpdateBatch = false;

                $batch->recordLoss(
                    $diff,
                    'Contaminación',
                    auth()->id() ?? $batch->user_id,
                    'Registro automático desde edición de lote'
                );

                \App\Observers\LossObserver::$shouldUpdateBatch = true;
            }
        }

        // --- PHASE TRANSITION (Edit Form) ---
        // Si phase_id viene seteado (desde el form) y es diferente a la fase actual logicamente esperada
        if ($batch->phase_id) {
            $currentPhaseId = $batch->current_phase?->id; // Nota: current_phase es un accessor que hace query
            // Si no lo tenemos cargado, usamos la relación relationLoaded check o query simple?
            // El accessor `getCurrentPhaseAttribute` hace query `phases()->wherePivot...`

            if ($currentPhaseId != $batch->phase_id) {
                $newPhase = \App\Models\Phase::find($batch->phase_id);
                if ($newPhase) {
                    // Hacemos la transición. Nota: transitionTo guarda cambios en pivote.
                    // Esto es seguro hacerlo aquí? Sí, no afecta la tabla batches directamente (excepto status si transition lo cambia?)
                    // transitionTo usa DB::transaction.
                    $batch->transitionTo($newPhase, 'Cambio manual de fase desde edición');
                }
            }
        }
    }

    /**
     * Calculates and sets the estimated harvest date based on inoculation date and strain.
     */
    private function setEstimatedHarvestDate(Batch $batch): void
    {
        if (!$batch->inoculation_date) {
            return;
        }

        $strain = $batch->strain;
        if (!$strain && $batch->strain_id) {
            $strain = \App\Models\Strain::find($batch->strain_id);
        }

        if ($strain && $strain->incubation_days) {
            $batch->estimated_harvest_date = \Carbon\Carbon::parse($batch->inoculation_date)
                ->addDays($strain->incubation_days);

            // Only log if we are actually calculating it (prevent spam if called multiple times)
            $code = $batch->code ?? 'N/A';
            Log::info("Fecha estimada de cosecha calculada: {$batch->estimated_harvest_date->format('Y-m-d')} para Lote {$code}");
        }
    }

    /**
     * Lógica para el Kanban: Evita lotes huérfanos
     */
    private function assignInitialPhase(Batch $batch): void
    {
        // 1. Intentamos obtenerlo de la propiedad pública del objeto (si se definió en el modelo)
        $phaseId = $batch->phase_id;

        // 2. Si es null, buscamos en la estructura compleja de Livewire/Filament
        if (!$phaseId) {
            $snapshot = request()->input('components.0.snapshot');
            if ($snapshot) {
                $decoded = json_decode($snapshot, true);

                // Según tu DD, los datos están en data -> data -> 0 -> phase_id
                $formData = $decoded['data']['data'] ?? [];

                // Verificamos si es un arreglo indexado (como en tu imagen) o asociativo
                $phaseId = isset($formData[0]['phase_id'])
                    ? $formData[0]['phase_id']
                    : ($formData['phase_id'] ?? null);
            }
        }

        if ($phaseId) {
            $batch->phases()->attach($phaseId, [
                'user_id' => $batch->user_id ?? (auth()->id() ?? 1),
                'started_at' => now(),
            ]);

            Log::info("Lote {$batch->code} asignado a fase ID: {$phaseId}");
        }
    }

    private function generateBatchCode(Batch $batch, bool $keepNumber = false)
    {
        // 1. Determinar el prefijo (Cepa o Tipo)
        if ($batch->strain_id) {
            $strainName = $batch->strain?->name ?? \App\Models\Strain::find($batch->strain_id)?->name;
            $prefix = strtoupper(substr(trim($strainName), 0, 3));
        } else {
            $prefix = $batch->type === 'grain' ? 'GRA' : 'SUB';
        }

        $datePart = now()->format('dmy');

        // 2. Determinar el número correlativo
        $nextNumber = 1;

        if ($keepNumber && $batch->code) {
            // Extraemos el número del código actual para intentar mantenerlo
            $parts = explode('-', $batch->code);
            $candidateNumber = (int) end($parts);

            // Verificamos si este número ya existe con el nuevo prefijo
            $candidateCode = "{$prefix}-{$datePart}-{$candidateNumber}";
            $exists = Batch::where('code', $candidateCode)
                ->where('id', '!=', $batch->id) // Ignorar el mismo lote
                ->exists();

            if (!$exists) {
                return $candidateCode;
            }
            // Si existe, fallamos al método estándar (buscar el último + 1)
        }

        // Método estándar: Buscamos el último consecutivo
        $lastBatch = Batch::where('code', 'like', "{$prefix}-{$datePart}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBatch) {
            $parts = explode('-', $lastBatch->code);
            $nextNumber = (int) end($parts) + 1;
        }

        return "{$prefix}-{$datePart}-{$nextNumber}";
    }

    private function deductInventory(Batch $batch): void
    {

        // Forzamos la carga de la receta si no está presente
        $recipe = $batch->recipe()->with('supplies')->first();

        if (!$recipe || $recipe->supplies->isEmpty()) {
            Log::warning("El lote {$batch->id} no tiene receta o insumos vinculados.");
            return;
        }

        // 1. CALCULAR PESO TOTAL HIDRATADO
        // El weight_dry es solo la parte seca (ej: 43%). Necesitamos el 100% para deducir correctamente el Agua y los demás.
        $dryPercentageSum = 0;

        foreach ($recipe->supplies as $supply) {
            if ($supply->pivot->calculation_mode === 'percentage' && stripos($supply->name, 'Agua') === false) {
                $dryPercentageSum += $supply->pivot->value;
            }
        }

        // Evitar división por cero
        $totalHydratedWeight = ($dryPercentageSum > 0)
            ? $batch->weigth_dry / ($dryPercentageSum / 100)
            : $batch->weigth_dry;

        Log::info("Cálculo de inventario:", [
            'peso_seco' => $batch->weigth_dry,
            'suma_porcentajes_secos' => $dryPercentageSum,
            'peso_total_calculado' => $totalHydratedWeight
        ]);

        foreach ($recipe->supplies as $supply) {
            // Accedemos explícitamente a los datos del pivote
            $mode = $supply->pivot->calculation_mode;
            $value = $supply->pivot->value;

            // Log de depuración interna para ver si los valores del pivote existen
            /*   Log::info("Procesando insumo: {$supply->name}", [
                   'modo' => $mode,
                   'valor_receta' => $value
               ]); */

            $amountToDeduct = ($mode === 'percentage')
                ? ($totalHydratedWeight * $value) / 100
                : $value * $batch->quantity;

            if ($amountToDeduct > 0) {
                // USAR decrement() directamente en la base de datos
                // Esto es más seguro y evita problemas de concurrencia
                $supply->decrement('quantity', $amountToDeduct);

                $batch->observations .= "\n- [Insumo] {$supply->name}: " . round($amountToDeduct, 4) . " {$supply->unit} descontados.";
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

        // 3. CALCULAR Y GUARDAR COSTO DE PRODUCCIÓN
        $estimatedCost = $recipe->getEstimatedCost($totalHydratedWeight, $batch->quantity);
        $batch->production_cost = $estimatedCost;
        $batch->observations .= "\n- [Financiero] Costo Estimado: $" . number_format($estimatedCost, 0);

        $batch->saveQuietly();
    }
}