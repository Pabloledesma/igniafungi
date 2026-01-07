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
    public $selectedBatchId;
    public $showModal = false;
    public $showLossModal = false;
    public $notes;
    public $nextPhaseId;
    public $isLastPhase = false;
    public $harvestWeight; 
    public $shouldFinishBatch = false; 
    public $harvestDate;

    public function mount()
    {
        $this->harvestDate = now()->format('Y-m-d'); // Fecha por defecto: hoy
    }
    
    public function openTransitionModal($batchId)
    {
        $this->selectedBatchId = $batchId;
        $batch = Batch::find($batchId);
        $currentPhase = $batch->current_phase;

        // Buscamos si existe una fase con orden superior
        $nextPhase = Phase::where('order', '>', $currentPhase->order)
                        ->orderBy('order')
                        ->first();

        if ($nextPhase) {
            $this->nextPhaseId = $nextPhase->id;
            $this->isLastPhase = false;
        } else {
            $this->nextPhaseId = null;
            $this->isLastPhase = true; // Estamos en la fase final (Cosecha)
        }

        $this->showModal = true;
    }

    public function close()
    {
        $this->reset(['selectedBatchId', 'showModal', 'notes', 'nextPhaseId']);
    }

    public function harvestBatch()
    {
        $this->validate([
            'harvestWeight' => 'required|numeric|min:0.1',
            'notes' => 'nullable|string'
        ]);

        $batch = Batch::find($this->selectedBatchId);

        DB::transaction(function () use ($batch) {
            // 1. Crear el recurso Harvest
            $batch->harvests()->create([
                'user_id' => auth()->id(),
                'weight' => $this->harvestWeight,
                'harvest_date' => now(), // o la fecha que elijas del modal
                'notes' => $this->notes,
                'phase_id' => $batch->current_phase->id,
            ]);

            // 2. Si el usuario marcó "Finalizar bloque"
            if ($this->shouldFinishBatch) {
                $batch->update(['status' => 'harvested']);
                
                // Cerrar la fase en la tabla pivote definitivamente
                $batch->phases()->updateExistingPivot($batch->current_phase->id, [
                    'finished_at' => now(),
                    'notes' => $this->notes . " (Bloque finalizado)"
                ]);
            } else {
                // Si NO finaliza, el lote se queda en la fase de Cosecha para el siguiente flush
                // Solo guardamos la nota de esta cosecha específica
                $currentNotes = $batch->current_phase->pivot->notes;
                $batch->phases()->updateExistingPivot($batch->current_phase->id, [
                    'notes' => $currentNotes . "\n- Cosecha (" . now()->format('d/m') . "): {$this->harvestWeight}g. " . $this->notes
                ]);
            }
        });

        $this->close();
        $this->reset(['shouldFinishBatch', 'harvestWeight']);
        LivewireAlert::title('Success')
            ->position('bottom-end')
            ->success()
            ->text("Cosecha registrada correctamente.")
            ->show();
    }

    public function finishBatch()
    {
        $this->validate([
            'harvestWeight' => 'required|numeric|min:0',
        ]);

        $batch = Batch::find($this->selectedBatchId);

        DB::transaction(function () use ($batch) {
            // 1. Marcar el lote como finalizado/cosechado
            $batch->update([
                'status' => 'harvested',
                'weight_dry' => $this->harvestWeight, // O el campo de peso que uses
            ]);

            // 2. Cerrar la última fase en la tabla pivote
            $batch->phases()->updateExistingPivot($batch->current_phase->id, [
                'finished_at' => now(),
                'notes' => $this->notes ?? 'Cosecha completada'
            ]);
        });

        $this->close();
        $this->dispatch('notify', 'Lote cosechado con éxito.');
    }

    public function confirmTransition()
    {
        $this->validate([
            'nextPhaseId' => 'required|exists:phases,id',
        ]);

        $batch = Batch::find($this->selectedBatchId);
        $nextPhase = Phase::find($this->nextPhaseId);

        // Ejecutamos la lógica del modelo que ya tiene TDD
        $batch->transitionTo($nextPhase, $this->notes);

        // Cerramos el modal y reseteamos variables
        $this->close();

        // Notificación opcional (si usas alguna librería de toasts)
        LivewireAlert::title('Success')
            ->position('bottom-end')
            ->success()
            ->text("Lote {$batch->code} movido a {$nextPhase->name}")
            ->show();
    }

    public function discardBatch($batchId, $reason, $qty)
    {
        $batch = Batch::find($batchId);
        
        DB::transaction(function () use ($batch, $reason, $qty) {
            // 1. Registrar la merma
            $batch->recordLoss($qty, $reason, auth()->id());
            
            // 2. Si la cantidad perdida es igual o mayor a la actual, inactivar lote
            if ($qty >= $batch->quantity) {
                $batch->update(['status' => 'contaminated']);
                // Cerramos la fase actual
                $batch->phases()->wherePivot('finished_at', null)->updateExistingPivot(
                    $batch->current_phase->id, 
                    ['finished_at' => now()]
                );
            }
        });

        $this->dispatch('notify', 'Lote reportado por contaminación.');
    }

    public function render()
    {
        return view('livewire.batch-kanban', [
            'phases' => Phase::orderBy('order')
                ->with(['batches' => function($query) {
                    $query->wherePivot('finished_at', null); // Solo lotes activos en esa fase
                }])->get()
        ]);
    }
}
