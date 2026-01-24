<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RecalculateBatchCosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:recalculate-costs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculates production cost for all existing batches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batches = \App\Models\Batch::with('recipe.supplies')->get();
        $count = 0;

        $this->info("Found {$batches->count()} batches.");

        foreach ($batches as $batch) {
            $recipe = $batch->recipe;

            if (!$recipe) {
                $this->warn("Batch {$batch->code} has no recipe. Skipping.");
                continue;
            }

            // 1. RE-CALCULAR PESO TOTAL HIDRATADO (Lógica espejo del Observer)
            // Nuevo modelo: initial_wet_weight es Peso Húmedo.
            $totalHydratedWeight = $batch->initial_wet_weight;

            // 2. Calcular Costo
            $cost = $recipe->getEstimatedCost($totalHydratedWeight, $batch->quantity);

            $batch->production_cost = $cost;
            $batch->saveQuietly();

            $count++;
            $this->line("Batch {$batch->code}: Cost updated to $" . number_format($cost, 2));
        }

        $this->info("Updated {$count} batches successfully.");
    }
}
