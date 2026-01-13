<?php

namespace Database\Seeders;

use App\Models\Supply;
use App\Models\SupplyCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Definir Categorías
        $categories = [
            'Sustratos' => [
                'Aserrín' => ['unit' => 'kg', 'stock' => 500, 'min' => 50],
                'Viruta' => ['unit' => 'kg', 'stock' => 500, 'min' => 50],
            ],
            'Suplementos' => [
                'Salvado de trigo' => ['unit' => 'kg', 'stock' => 100, 'min' => 10],
                'Cal' => ['unit' => 'kg', 'stock' => 50, 'min' => 5],
                'Yeso' => ['unit' => 'kg', 'stock' => 50, 'min' => 5],
            ],
            'Material de Laboratorio' => [
                'Cajas Petri' => ['unit' => 'unidades', 'stock' => 100, 'min' => 20],
                'Guantes de nitrilo' => ['unit' => 'unidades', 'stock' => 100, 'min' => 20],
                'Cuchillas bisturi' => ['unit' => 'unidades', 'stock' => 100, 'min' => 20],
                'Alcohol' => ['unit' => 'litros', 'stock' => 10, 'min' => 2],
                'Agar Agar' => ['unit' => 'gr', 'stock' => 500, 'min' => 50],
                'Extracto de malta' => ['unit' => 'gr', 'stock' => 500, 'min' => 50],
                'Papel aluminio' => ['unit' => 'unidades', 'stock' => 100, 'min' => 50],
                'Geringas' => ['unit' => 'unidades', 'stock' => 100, 'min' => 50],
                'Agujas 16G' => ['unit' => 'unidades', 'stock' => 100, 'min' => 50],
            ],
            'Empaque' => [
                'Bolsas de polipropileno' => ['unit' => 'unidades', 'stock' => 1000, 'min' => 200],
                'Frascos de vidrio' => ['unit' => 'unidades', 'stock' => 200, 'min' => 20],
            ],
        ];

        foreach ($categories as $catName => $supplies) {
            // Verificar si la categoría ya existe o crearla
            $category = SupplyCategory::firstOrCreate(
                ['slug' => Str::slug($catName)],
                ['name' => $catName]
            );

            foreach ($supplies as $supplyName => $data) {
                // Crear insumo vinculado a la categoría
                Supply::firstOrCreate(
                    [
                        'name' => $supplyName,
                        'supply_category_id' => $category->id,
                    ],
                    [
                        'unit' => $data['unit'],
                        'quantity' => $data['stock'], // current_stock mapping
                        'min_stock' => $data['min'],
                        'cost_per_unit' => rand(1000, 50000) / 100, // Precio simulado
                    ]
                );
            }
        }
    }
}
