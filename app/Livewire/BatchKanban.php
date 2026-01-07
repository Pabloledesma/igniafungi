<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Phase;
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

        if ($nextPhase) {
            $this->nextPhaseId = $nextPhase->id;
            $this->isLastPhase = false;
        } else {
            $this->nextPhaseId = null;
            $this->isLastPhase = true;
        }

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
            $this->alertSuccess('Merma registrada y stock actualizado.');
        }
    }

    public function harvestBatch()
    {
        $this->validate([
            'harvestWeight' => 'required|numeric|min:0.1',
            'harvestDate' => 'required|date',
            'notes' => 'nullable|string'
        ]);

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
        $this->alertSuccess("Cosecha registrada correctamente.");
    }

    public function confirmTransition()
    {
        $batch = Batch::find($this->selectedBatchId);
        $nextPhase = Phase::find($this->nextPhaseId);
        
        // Validación: Si pasa a incubación, debe tener genética
        if ($nextPhase && $nextPhase->slug === 'inoculation' && is_null($batch->strain_id)) {
            $this->addError('strain_id', 'Debes asignar una genética antes de inocular e iniciar incubación.');
            return;
        }
        
        $this->validate(['nextPhaseId' => 'required|exists:phases,id']);
        $batch->transitionTo($nextPhase, $this->notes);
        $this->close();
        $this->alertSuccess("Lote {$batch->code} movido a {$nextPhase->name}");
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
        $this->alertSuccess("El descarte se ha registrado correctamente.");
    }

    // --- UTILIDADES ---

    public function close()
    {
        $this->reset(['selectedBatchId', 'showModal', 'notes', 'nextPhaseId', 'harvestWeight', 'shouldFinishBatch']);
    }

    public function closeDiscard()
    {
        $this->showDiscardModal = false;
        $this->reset(['discardQuantity', 'discardReason', 'discardNotes', 'selectedBatchId']);
    }

    private function alertSuccess($message)
    {
        LivewireAlert::title('Éxito')
            ->position('bottom-end')
            ->success()
            ->text($message)
            ->show();
    }

    public function render()
    {
        return view('livewire.batch-kanban', [
            'phases' => Phase::orderBy('order')
                ->with(['batches' => function($query) {
                    // Incluimos todos los estados que representan un lote en producción
                    $query->whereIn('batches.status', ['active', 'contaminated', 'finalized'])
                        ->wherePivot('finished_at', null);
                    // Aplicar filtro si no está vacío
                    if ($this->batchType) {
                        $query->where('type', $this->batchType);
                    }
                }])->get()
        ]);
    }
}