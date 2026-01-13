<?php

namespace Database\Seeders;

use App\Models\Recipe;
use App\Models\Supply;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Receta de Sustrato Estándar (Master's Mix o similar enriquecido)
        $substrateRecipe = Recipe::firstOrCreate(
            ['name' => 'Sustrato Estándar Enriquecido (Nanakati)'],
            ['type' => 'substrate']
        );

        // Buscar insumos necesarios
        $sawdust = Supply::where('name', 'Aserrín')->first();
        $viruta = Supply::where('name', 'Viruta')->first();
        $bran = Supply::where('name', 'Salvado de trigo')->first();
        $gypsum = Supply::where('name', 'Yeso')->first();
        $lime = Supply::where('name', 'Cal')->first();

        // Adjuntar insumos si existen
        if ($sawdust && $bran && $gypsum && $lime && $viruta) {
            $substrateRecipe->supplies()->syncWithoutDetaching([
                $sawdust->id => ['calculation_mode' => 'percentage', 'value' => 24],
                $viruta->id => ['calculation_mode' => 'percentage', 'value' => 11],
                $bran->id => ['calculation_mode' => 'percentage', 'value' => 6],
                $gypsum->id => ['calculation_mode' => 'percentage', 'value' => 1.5],
                $lime->id => ['calculation_mode' => 'percentage', 'value' => 0.5],
            ]);
        }

        // 2. Receta de Agar PDA (Papa Dextrosa Agar - Simulado con Malta/Agar)
        $agarRecipe = Recipe::firstOrCreate(
            ['name' => 'Agar Malta (MEA)'],
            ['type' => 'culture_media']
        );

        $agar = Supply::where('name', 'Agar Agar')->first();
        $malt = Supply::where('name', 'Extracto de malta')->first();

        if ($agar && $malt) {
            $agarRecipe->supplies()->syncWithoutDetaching([
                $agar->id => ['calculation_mode' => 'fixed_per_unit', 'value' => 20], // 20g
                $malt->id => ['calculation_mode' => 'fixed_per_unit', 'value' => 20], // 20g
            ]);
        }
    }
}
