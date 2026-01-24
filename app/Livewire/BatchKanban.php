<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\Strain;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

#[Layout('components.layouts.erp')]
class BatchKanban extends Component
{
    // Modales y Estados
    public $showModal = false;
    public $showDiscardModal = false;
    public $showLossModal = false;
    public $isLastPhase = false;
    public $isTotalDiscard = false;

    // Propiedades generales
    public $selectedBatchId;
    public $notes = '';
    public $nextPhaseId;
    public $strainId;
    public $strains = [];

    // Propiedades de Cosecha
    public $harvestWeight;
    public $harvestDate;
    public $shouldFinishBatch = false;

    // Propiedades de Descarte
    public $discardQuantity;
    public $discardReason;
    public $discardNotes;

    public $lossQuantity;
    public $lossReason;
    public $lossDetails;
    public $batchType = ''; // Puede ser '', 'grain' o 'bulk'
    public $showHarvestFields = false;

    // Filtros
    public $search = '';
    public $selectedStrain = '';

    // Propiedades de Inoculación
    public $inoculumBatchId;
    public $inoculumRatio = 10; // Default 10%
    public $availableInoculumBatches = [];

    public function mount()
    {
        $this->harvestDate = now()->format('Y-m-d');
    }

    // --- LÓGICA DE TRANSICIÓN Y COSECHA ---

    public function openTransitionModal($batchId)
    {
        $this->selectedBatchId = $batchId;
        $batch = Batch::find($batchId);
        $currentPhase = $batch->current_phase;

        $nextPhase = Phase::where('order', '>', $currentPhase->order)
            ->orderBy('order')
            ->first();

        // Si vamos a inocular, cargamos las cepas
        if ($nextPhase && $nextPhase->slug === 'inoculation') {
            $this->strains = Strain::all();
        } else {
            $this->strains = [];
        }

        if ($nextPhase) {
            $this->nextPhaseId = $nextPhase->id;
            $this->isLastPhase = false;
        } else {
            $this->nextPhaseId = null;
            $this->isLastPhase = true;
        }
        $this->showHarvestFields = ($currentPhase->slug === 'fruiting' || $currentPhase->slug === 'incubation');
        $this->showModal = true;
    }

    public function saveLoss()
    {
        $this->validate([
            'lossQuantity' => 'required|numeric|min:0.1',
            'lossReason' => 'required|string',
            'lossDetails' => 'nullable|string',
        ]);

        $batch = Batch::find($this->selectedBatchId);

        if ($batch) {
            $batch->recordLoss(
                $this->lossQuantity,
                $this->lossReason,
                auth()->id(),
                $this->lossDetails
            );

            $this->reset(['lossQuantity', 'lossReason', 'lossDetails', 'selectedBatchId']);
            $this->dispatch('close-modal'); // O el método que uses para cerrar modales
            $this->alert('Merma registrada y stock actualizado.', 'success');
        }
    }

    public function harvestBatch()
    {
        try {
            $this->validate([
                'harvestWeight' => 'required|numeric|min:0.01',
                'harvestDate' => 'required|date',
                'notes' => 'nullable|string'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Esto te mostrará en la consola del navegador qué campo falta
            $this->alert($e->validator->errors(), 'error');
        }

        $batch = Batch::findOrFail($this->selectedBatchId);

        DB::transaction(function () use ($batch) {
            // 1. Crear el recurso Harvest (Usando tu modelo Harvest)
            $batch->harvests()->create([
                'user_id' => auth()->id(),
                'weight' => $this->harvestWeight,
                'harvest_date' => $this->harvestDate,
                'notes' => $this->notes,
                'phase_id' => $batch->current_phase->id,
            ]);

            if ($this->shouldFinishBatch) {
                $batch->update(['status' => 'harvested']);

                $batch->phases()->updateExistingPivot($batch->current_phase->id, [
                    'finished_at' => now(),
                    'notes' => $this->notes . " (Bloque finalizado)"
                ]);
            } else {
                // Registro de nota en el pivote para histórico
                $currentNotes = $batch->current_phase->pivot->notes;
                $batch->phases()->updateExistingPivot($batch->current_phase->id, [
                    'notes' => $currentNotes . "\n- Cosecha (" . $this->harvestDate . "): {$this->harvestWeight} kg. " . $this->notes
                ]);
            }
        });

        $this->close();
        $this->alert("Cosecha registrada correctamente.", 'success');
    }

    public function confirmTransition()
    {
        // 1. Ejecutamos la validación estándar primero
        $this->validate([
            'nextPhaseId' => 'required|exists:phases,id',
        ]);
        $batch = Batch::find($this->selectedBatchId);
        $nextPhase = Phase::find($this->nextPhaseId);

        // Validación: Si pasa a inoculacion, debe tener genética
        if ($nextPhase && $nextPhase->slug === 'inoculation') {
            // Actualizamos la cepa primero si viene en el input
            if ($this->strainId) {
                $batch->strain_id = $this->strainId; // Seteamos en memoria para validar
            }

            if (!$batch->canTransitionToInoculation()) {
                $this->addError('strainId', 'Debes asignar una genética antes de inocular.');
                return;
            }

            // Validar Seed Batch si se seleccionó
            if ($this->inoculumBatchId) {
                $this->validate([
                    'inoculumBatchId' => 'exists:batches,id',
                    'inoculumRatio' => 'required|numeric|min:5|max:20',
                ]);
            }

            // Si pasó, guardamos la cepa si es nueva
            if ($batch->isDirty('strain_id')) {
                $batch->save();
            }

            // Actualizamos fecha de inoculación si no existe
            if (!$batch->inoculation_date) {
                $batch->inoculation_date = now();
                $batch->save();
            }

            // --- INVENTORY DEDUCTION (Specifc Seed Batch) ---
            if ($this->inoculumBatchId) {
                $inoculumBatch = Batch::find($this->inoculumBatchId);
                if ($inoculumBatch) {
                    $targetWetWeight = $batch->initial_wet_weight;
                    $ratio = $this->inoculumRatio;

                    // Calculation: Weight needed = TargetWet * (Ratio/100)
                    $neededWeight = $targetWetWeight * ($ratio / 100);

                    // Convert to Units of Inoculum (Bags/Jars)
                    $seedBagWeight = $inoculumBatch->bag_weight > 0 ? $inoculumBatch->bag_weight : $inoculumBatch->initial_wet_weight / max($inoculumBatch->quantity, 1);

                    if ($seedBagWeight > 0) {
                        $unitsToDeduct = $neededWeight / $seedBagWeight;

                        // VALIDATION: Check Stock
                        if ($inoculumBatch->quantity < $unitsToDeduct) {
                            $this->addError('inoculumBatchId', "Stock de semilla insuficiente para el % de siembra requerido. (Requerido: " . round($unitsToDeduct, 2) . " u. Disponible: {$inoculumBatch->quantity} u)");
                            return;
                        }

                        // Deduct
                        $inoculumBatch->decrement('quantity', $unitsToDeduct);

                        // Observations
                        $inoculumBatch->observations .= "\n- [Uso Semilla] Usado para inocular lote {$batch->code}. {$unitsToDeduct} u ({$neededWeight}kg) descontados.";
                        $inoculumBatch->saveQuietly();

                        // Specific Log Format
                        $batch->observations .= "\n- Sembrado con {$ratio}% de semilla del lote {$inoculumBatch->code}.";
                        $batch->saveQuietly();
                    }
                }
            }

        }

        $batch->transitionTo($nextPhase, $this->notes);
        $this->close();
        $this->alert("Lote {$batch->code} movido a {$nextPhase->name}", 'success');
    }

    // --- LÓGICA DE DESCARTE (QUINTAR BLOQUES) ---

    public function openDiscardModal($batchId)
    {
        $this->selectedBatchId = $batchId;
        $this->showDiscardModal = true;
        $this->discardQuantity = 0;
        $this->discardReason = 'Contaminación';
        $this->isTotalDiscard = false;
    }

    public function updatedIsTotalDiscard($value)
    {
        if ($value && $this->selectedBatchId) {
            // Traemos solo el valor de la columna quantity, sin hidratar el modelo completo
            $this->discardQuantity = Batch::where('id', $this->selectedBatchId)->value('quantity');
        } else {
            $this->discardQuantity = 0;
        }
    }

    public function processDiscard()
    {
        $id = $this->selectedBatchId;

        if (is_array($id)) {
            // Si es un array tipo ['id' => 5] o [0 => 5], sacamos el valor
            $id = collect($id)->flatten()->first();
        }

        // 2. Verificación de seguridad
        if (!$id) {
            $this->alertError("No se pudo identificar el lote. Por favor intenta de nuevo.");
            return;
        }

        $batch = Batch::findOrFail($id);

        // Si es descarte total, forzamos la cantidad del lote
        if ($this->isTotalDiscard) {
            $this->discardQuantity = $batch->quantity;
        }

        $this->validate([
            'discardQuantity' => 'required|numeric|min:0.1|max:' . $batch->quantity,
            'discardReason' => 'required|string',
        ]);

        DB::transaction(function () use ($batch) {
            // 1. Registrar la pérdida en tu tabla de mermas
            $batch->recordLoss(
                $this->discardQuantity,
                $this->discardReason,
                auth()->id(),
                $this->discardNotes
            );

            // 2. Si el lote queda en 0 o se marcó descarte total, se saca del Kanban
            if ($this->isTotalDiscard || $this->discardQuantity >= $batch->quantity) {
                // Si el motivo es Agotado usamos 'finalized', si no 'contaminated'
                $newStatus = ($this->discardReason === 'Agotado') ? 'finalized' : 'contaminated';

                $batch->update(['status' => $newStatus]);

                // Cerramos la fase actual
                $batch->phases()->wherePivot('finished_at', null)->updateExistingPivot(
                    $batch->current_phase->id,
                    ['finished_at' => now()]
                );
            }
        });

        $this->closeDiscard();
        $this->alert("El descarte se ha registrado correctamente.", 'success');
    }

    // --- UTILIDADES ---

    public function close()
    {
        $this->reset(['selectedBatchId', 'showModal', 'notes', 'nextPhaseId', 'harvestWeight', 'shouldFinishBatch', 'strainId', 'strains', 'inoculumBatchId', 'inoculumRatio', 'availableInoculumBatches']);
    }

    public function updatedStrainId($value)
    {
        if (!$value) {
            $this->availableInoculumBatches = [];
            return;
        }

        $batches = Batch::query()
            ->where('strain_id', $value)
            ->where('type', 'grain') // Semilla
            ->where('status', 'active') // Filter 2: Active
            ->whereNotNull('inoculation_date')
            // Filter 3: Inoculation Date <= 20 days ago (Mature)
            ->where('inoculation_date', '<=', now()->subDays(20))
            ->whereHas('phases', function ($q) {
                // Filter 4: Phase 'Incubación' (Active)
                $q->where('slug', 'incubation')
                    ->whereNull('finished_at');
            })
            ->orderBy('inoculation_date', 'asc') // FIFO
            ->get()
            ->map(function ($batch) {
                // Display: Date + Days Elapsed + Quantity
                $days = $batch->inoculation_date->diffInDays(now());
                $batch->formatted_label = "{$batch->code} - {$batch->inoculation_date->format('d/m/Y')} ({$days}d) - {$batch->quantity} u";
                return $batch;
            });

        $this->availableInoculumBatches = $batches;

        if ($batches->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Semilla no disponible')
                ->body('No hay lotes de semilla de esta cepa con más de 20 días de incubación.')
                ->persistent()
                ->send();
        }
    }

    public function closeDiscard()
    {
        $this->showDiscardModal = false;
        $this->reset(['discardQuantity', 'discardReason', 'discardNotes', 'selectedBatchId']);
    }

    private function alert($message, $messageType = 'success')
    {
        if ($messageType == 'success') {
            LivewireAlert::title('Éxito')
                ->position('bottom-end')
                ->success()
                ->text($message)
                ->show();

        }

        if ($messageType == 'error') {
            LivewireAlert::title('Error')
                ->position('bottom-end')
                ->error()
                ->text($message)
                ->show();
        }
    }

    public function render()
    {
        return view('livewire.batch-kanban', [
            'phases' => Phase::orderBy('order')
                ->with([
                    'batches' => function ($query) {
                        // Incluimos todos los lotes que tengan una fase activa (finished_at = null)
                        $query->wherePivot('finished_at', null)
                            ->active() // Filter by valid status
                            ->withCount('harvests');
                        if ($this->batchType) {
                            $query->where('type', $this->batchType);
                        }

                        if ($this->search) {
                            $query->where('code', 'like', '%' . $this->search . '%');
                        }

                        if ($this->selectedStrain) {
                            $query->where('strain_id', $this->selectedStrain);
                        }
                    }
                ])->get(),
            'allStrains' => Strain::orderBy('name')->get()
        ]);
    }
}