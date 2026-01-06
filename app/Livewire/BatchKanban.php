<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Phase;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.erp')]
class BatchKanban extends Component
{
    public $selectedBatchId;
    public $showModal = false;
    public $notes;
    public $nextPhaseId;

    // Método que disparó el error
    public function openTransitionModal($batchId)
    {
        $this->selectedBatchId = $batchId;
        // Pre-calculamos la siguiente fase lógica
        $batch = Batch::find($batchId);
        $currentPhase = $batch->current_phase;
        $this->nextPhaseId = Phase::where('order', '>', $currentPhase->order)
                                  ->orderBy('order')
                                  ->first()?->id;
         $this->showModal = true;
    }

    public function close()
    {
        $this->reset(['selectedBatchId', 'showModal', 'notes', 'nextPhaseId']);
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
        session()->flash('success', "Lote {$batch->code} movido a {$nextPhase->name}");
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
