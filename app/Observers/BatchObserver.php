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

        // --- AUTOMATIC HISTORICAL LOSS REGISTRATION ---
        // Si el lote nace "muerto" (descartado/contaminado), registramos la pérdida inicial
        if (in_array($batch->status, ['discarded', 'contaminated'])) {
            $this->createHistoricalLossRecord($batch);
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

        // --- SYNC HISTORICAL QUANTITY ---
        if ($batch->isDirty('quantity')) {
            // If we change the quantity of a historical contaminated batch, we likely want to update the loss record
            // Logic: Find a single loss record categorized as 'Legado / Histórico'
            $historicalLoss = $batch->losses()->where('reason', 'Legado / Histórico')->first();

            if ($historicalLoss) {
                $historicalLoss->update(['quantity' => $batch->quantity]);
                Log::info("Sincronizada cantidad de pérdida histórica para Lote {$batch->code}: {$batch->quantity}");
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
     * Creates a BatchLoss record for historical batches initialized as failed.
     */
    private function createHistoricalLossRecord(Batch $batch): void
    {
        // 1. Determine Phase
        // User requested 'Incubación Semilla' for grain, else general logic.
        // We try to find a matching Phase.
        $phaseName = ($batch->type === 'grain') ? 'Incubación' : 'Incubación'; // Ajustado a fases reales del sistema

        $phase = \App\Models\Phase::where('name', 'like', "%{$phaseName}%")->first()
            ?? \App\Models\Phase::orderBy('order')->first();

        if (!$phase) {
            Log::warning("No se pudo registrar pérdida histórica para Lote {$batch->code}: No hay fases en el sistema.");
            return;
        }

        // 2. Reason
        // "Motivo Principal" should be the categorization
        $reason = 'Legado / Histórico';

        // "Motivo Detallado" (details) comes from observations
        $details = !empty($batch->observations)
            ? $batch->observations
            : 'Registro Histórico / Contaminación Inicial';

        // 3. Create Loss
        \App\Models\BatchLoss::create([
            'batch_id' => $batch->id,
            'phase_id' => $phase->id,
            'quantity' => $batch->quantity,
            'reason' => $reason,
            'details' => $details . ". Original Date: " . ($batch->inoculation_date?->format('Y-m-d') ?? 'N/A'),
            'user_id' => auth()->id() ?? $batch->user_id,
            'created_at' => $batch->inoculation_date ?? now(),
            'updated_at' => now(),
        ]);

        Log::info("Pérdida histórica registrada automáticamente para Lote {$batch->code}");
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

        // Histórico: Si el estado NO es activo (es decir, completado, descartado, etc.) al crear
        if ($batch->status !== 'active') {
            // Opcional: Prefijo H- para históricos
            // $prefix = "H-" . $prefix; 
        }

        // 2. Determinar la fecha (Inoculation Date o Now)
        // User requested: inoc_date if available. Format DMY (e.g. 100625) as legacy format
        $dateSource = $batch->inoculation_date ?? now();
        $datePart = $dateSource->format('dmy');

        // 3. Determinar el número correlativo
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
        // SQLite no soporta SUBSTRING_INDEX, así que mejor obtenemos la lista y calculamos en PHP
        // Dado que rara vez habrá mas de 10-20 lotes por día/cepa, esto es eficiente.
        $codes = Batch::where('code', 'like', "{$prefix}-{$datePart}-%")
            ->pluck('code');

        if ($codes->isNotEmpty()) {
            $maxNum = 0;
            foreach ($codes as $code) {
                $parts = explode('-', $code);
                $num = (int) end($parts);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
            $nextNumber = $maxNum + 1;
        }

        return "{$prefix}-{$datePart}-{$nextNumber}";
    }

    private function deductInventory(Batch $batch): void
    {

        // 0. GUARD CLAUSE: Only deduct inventory for NEW ACTIVE batches.
        // Historical batches (completed/finalized) should NOT affect current stock.
        if ($batch->status !== 'active') {
            Log::info("Lote {$batch->code}: Omitiendo descuento de inventario por ser histórico/no-activo (Status: {$batch->status}).");
            return;
        }

        // Forzamos la carga de la receta si no está presente
        $recipe = $batch->recipe()->with('supplies')->first();

        if (!$recipe || $recipe->supplies->isEmpty()) {
            Log::warning("El lote {$batch->id} no tiene receta o insumos vinculados.");
            return;
        }

        // 1. CALCULAR PESO SECO (Nuevo Modelo)
        // El batch->initial_wet_weight ahora representa el PESO HÚMEDO TOTAL (Input usuario)
        $wetWeight = $batch->initial_wet_weight;
        $dryRatio = $recipe->dry_weight_ratio ?? 0.40; // Default 40%

        // Peso Sólido (Materia Seca)
        $dryWeight = $wetWeight * $dryRatio;

        Log::info("Cálculo de inventario (Nuevo Modelo):", [
            'peso_humedo_input' => $wetWeight,
            'ratio_solidos' => $dryRatio,
            'masa_seca_calculada' => $dryWeight
        ]);

        foreach ($recipe->supplies as $supply) {
            // Accedemos explícitamente a los datos del pivote
            $mode = $supply->pivot->calculation_mode;
            $value = $supply->pivot->value;

            // Fórmula solicitada: (Peso Total Húmedo * dry_weight_ratio) * (Porcentaje del Insumo / 100)
            // Que equivale a: $dryWeight * ($value / 100)

            $amountToDeduct = 0;

            if ($mode === 'percentage') {
                $amountToDeduct = $dryWeight * ($value / 100);
            } elseif ($mode === 'fixed_per_unit') {
                $amountToDeduct = $value * $batch->quantity;
            }

            if ($amountToDeduct > 0) {
                // USAR decrement() directamente en la base de datos
                $supply->decrement('quantity', $amountToDeduct);

                $batch->observations .= "\n- [Insumo] {$supply->name}: " . round($amountToDeduct, 4) . " {$supply->unit} descontados.";
            }
        }

        // Variable para usar en el costo
        $totalHydratedWeight = $wetWeight;

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