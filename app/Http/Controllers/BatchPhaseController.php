<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Phase;
use Illuminate\Http\Request;

class BatchPhaseController
{
    // BatchPhaseController.php

    public function transition(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'phase_id' => 'required|exists:phases,id',
            'notes' => 'nullable|string'
        ]);

        if ($request->has('weight') && $request->weight > 0) {
            $batch->harvests()->create([
                'weight' => $request->weight,
                'date' => now(),
                // otros campos como calidad...
            ]);
        }

        $nextPhase = Phase::find($validated['phase_id']);
        
        // Nuestra lógica ya testeada
        $batch->transitionTo($nextPhase, $validated['notes']);

        return back()->with('success', "El lote ha avanzado a la fase: {$nextPhase->name}");
    }
}
