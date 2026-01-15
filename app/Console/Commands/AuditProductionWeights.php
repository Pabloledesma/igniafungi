<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditProductionWeights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:production-weights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audits production data for weight anomalies and recalculates costs.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Production Data Audit...');

        $suspiciousBatches = [];
        $suspiciousHarvests = [];
        $recalculatedBatches = 0;

        // 1. Audit Batches
        $this->info('Scanning Batches...');
        $batches = \App\Models\Batch::with('recipe')->get();

        foreach ($batches as $batch) {
            // Check bag_weight (Limit: 20kg per unit)
            if ($batch->bag_weight > 20) {
                $suspiciousBatches[] = [
                    'id' => $batch->id,
                    'code' => $batch->code,
                    'bag_weight' => $batch->bag_weight . ' kg',
                    'issue' => 'Bag weight > 20kg (Likely grams entered)'
                ];
                continue; // Skip recalculation
            }

            // Recalculate Cost if Recipe exists
            if ($batch->recipe) {
                // Assuming bag_weight is correct, total hydrated weight = quantity * bag_weight
                // Or does Recipe take total weight? Recipe::getEstimatedCost($totalWeight)
                $totalWeight = $batch->quantity * $batch->bag_weight;
                $newCost = $batch->recipe->getEstimatedCost($totalWeight, $batch->quantity);

                if (abs($batch->production_cost - $newCost) > 0.01) {
                    $batch->production_cost = $newCost;
                    $batch->save();
                    $recalculatedBatches++;
                }
            }
        }

        // 2. Audit Harvests
        $this->info('Scanning Harvests...');
        $harvests = \App\Models\Harvest::with('batch')->get();

        foreach ($harvests as $harvest) {
            if ($harvest->weight > 5) {
                $suspiciousHarvests[] = [
                    'id' => $harvest->id,
                    'batch_code' => $harvest->batch?->code ?? 'N/A',
                    'weight' => $harvest->weight . ' kg',
                    'issue' => 'Harvest weight > 5kg (Likely grams entered)'
                ];
            }
        }

        // 3. Report
        $this->newLine();
        $this->info("Audit Complete.");
        $this->info("Recalculated Costs for {$recalculatedBatches} batches.");

        if (count($suspiciousBatches) > 0) {
            $this->error(count($suspiciousBatches) . ' Suspicious Batches Found:');
            $this->table(['ID', 'Code', 'Weight', 'Issue'], $suspiciousBatches);
        } else {
            $this->info('No suspicious batches found.');
        }

        if (count($suspiciousHarvests) > 0) {
            $this->error(count($suspiciousHarvests) . ' Suspicious Harvests Found:');
            $this->table(['ID', 'Batch', 'Weight', 'Issue'], $suspiciousHarvests);
        } else {
            $this->info('No suspicious harvests found.');
        }

        // Clear cache as requested
        $this->call('cache:clear');
        $this->info('System cache cleared.');
    }
}
