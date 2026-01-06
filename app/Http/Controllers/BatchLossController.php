<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\Request;

class BatchLossController extends Controller
{
    public function store(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string'
        ]);

        // Usamos el método recordLoss que definimos antes en el modelo Batch
        $batch->recordLoss(
            $validated['quantity'], 
            $validated['reason'], 
            auth()->id(),
            $validated['details'] ?? null
        );

        return back()->with('success', "Merma registrada correctamente.");
    }
}