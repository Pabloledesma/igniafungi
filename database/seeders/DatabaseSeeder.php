<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Batch;
use App\Models\Phase;
use App\Models\Recipe;
use App\Models\Strain;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            UserSeeder::class,
            PhaseSeeder::class,
            ProductSeeder::class,
            SupplySeeder::class,
        ]);

        $phases = Phase::orderBy('order')->get();
        $strains = Strain::all();
        $recipe = Recipe::factory()->create(['name' => 'Receta Base Estándar']);
        $pablo = User::where('email', 'gerencia@igniafungi.com')->first();

        foreach ($phases as $phase) {
            // Definimos si esta fase requiere cepa (Incubación y Fructificación)
            $requiresStrain = in_array($phase->slug, ['incubation', 'fruiting']);

            // Creamos 2 lotes por cada fase
            for ($i = 0; $i < 2; $i++) {
                $batch = Batch::create([
                    'user_id' => $pablo->id,
                    'recipe_id' => $recipe->id,
                    // Solo asignamos cepa si la fase lo requiere
                    'strain_id' => $requiresStrain ? $strains->random()->id : null,
                    'quantity' => rand(20, 50),
                    'weigth_dry' => rand(10, 30),
                    // La fecha de inoculación solo tiene sentido si ya se inoculó
                    'inoculation_date' => $requiresStrain ? now()->subDays(rand(5, 20)) : null,
                    'status' => $phase->slug,
                ]);

                // Asociar la fase al lote en la tabla pivote
                $batch->phases()->attach($phase->id, [
                    'user_id' => $pablo->id,
                    'started_at' => now()->subDays(rand(1, 10)),
                    'notes' => "Lote en fase de {$phase->name} generado por el seeder."
                ]);

                // Registrar pérdida solo si ya hay micelio/setas (opcional)
                if ($phase->slug === 'incubation' && $i === 0) {
                    $batch->recordLoss(5, 'Contaminación detectada', $pablo->id, 'Moho detectado en incubación.');
                }
            }
        }
    }

}
