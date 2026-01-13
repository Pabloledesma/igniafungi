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
            $dryPercentageSum = 0;
            foreach ($recipe->supplies as $supply) {
                if ($supply->pivot->calculation_mode === 'percentage' && stripos($supply->name, 'Agua') === false) {
                    $dryPercentageSum += $supply->pivot->value;
                }
            }

            $totalHydratedWeight = ($dryPercentageSum > 0)
                ? $batch->weigth_dry / ($dryPercentageSum / 100)
                : $batch->weigth_dry;

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
