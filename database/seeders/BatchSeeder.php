<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Phase;
use App\Models\Strain;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    public function run()
    {
        $strains = Strain::all();
        $phases = Phase::all()->keyBy('slug');

        if ($strains->isEmpty()) {
            $this->command->warn('No strains found. Run StrainSeeder first.');
            return;
        }

        // 1. Incubation (2 per strain)
        foreach ($strains as $strain) {
            for ($i = 0; $i < 2; $i++) {
                // Inoculated recently
                $this->createBatch($strain, $phases['incubation'], now()->subDays(rand(1, 5)));
            }
        }

        // 2. Fruiting (4 per strain)
        foreach ($strains as $strain) {
            for ($i = 0; $i < 4; $i++) {
                // Inoculated a while ago (e.g. 30 days)
                $this->createBatch($strain, $phases['fruiting'], now()->subDays(rand(30, 60)));
            }
        }

        // 3. Preparation (10 total)
        for ($i = 0; $i < 10; $i++) {
            $strain = $strains->random();
            $this->createBatch($strain, $phases['preparation'], null);
        }

        // 4. Cooling (10 total)
        for ($i = 0; $i < 10; $i++) {
            $strain = $strains->random();
            $this->createBatch($strain, $phases['cooling'], null);
        }
        // 5. GRATIN (Mature Grain Batches for Expansion)
        // Inoculated > 20 days ago
        foreach ($strains as $strain) {
            for ($i = 0; $i < 3; $i++) {
                $this->createBatch($strain, $phases['incubation'], now()->subDays(rand(21, 35)), 'grain');
            }
        }
    }

    private function createBatch($strain, $phase, $inoculationDate, $type = 'bulk')
    {
        // Create the batch initially without inoculation date to trigger observer cleanly later if needed,
        // OR create with it if we move logic to 'saving'. 
        // Current logic is in 'updating'. so we must create then update.

        $batch = Batch::create([
            'strain_id' => $strain->id,
            'user_id' => 1,
            'quantity' => 10,
            'initial_wet_weight' => 20, // 20kg Total Wet Weight (example)
            'bag_weight' => 2.0, // 2kg per block as requested
            'expected_yield' => 10 * 0.5, // 500g per block (Example assumption)
            'type' => $type,
            'status' => 'active',
            'observations' => 'Seeded automatically via BatchSeeder',
            // We don't set inoculation_date here to ensure 'updating' event catches it next
        ]);

        // Attach Phase
        if ($phase) {
            $batch->phases()->attach($phase->id, [
                'user_id' => 1,
                'started_at' => $inoculationDate ?? now(),
            ]);
        }

        // If we have an inoculation date, set it now.
        // This triggers the 'updating' observer which calculates estimated_harvest_date
        if ($inoculationDate) {
            $batch->inoculation_date = $inoculationDate;
            $batch->save();
        }
    }
}
