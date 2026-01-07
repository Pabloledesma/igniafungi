<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PhaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $phases = [
            [
                'name' => 'Preparación', 
                'slug' => 'preparation', 
                'order' => 1,
                'description' => 'Mezcla, empaque y esterilización del sustrato'
            ],
            [
                'name' => 'Enfriamiento', 
                'slug' => 'cooling', 
                'order' => 2,
                'description' => 'Sustrato alcanzando temperatura ambiente'
            ],
            [
                'name' => 'Inoculación', 
                'slug' => 'inoculation', 
                'order' => 3,
                'description' => 'Crecimiento del micelio post-inoculación'
            ],
            [
                'name' => 'Incubación', 
                'slug' => 'incubation', 
                'order' => 4,
                'description' => 'Crecimiento del micelio post-inoculación'
            ],
            [
                'name' => 'Fructificación', 
                'slug' => 'fruiting', 
                'order' => 5,
                'description' => 'Producción de oleadas y cosecha'
            ],
        ];

        foreach ($phases as $phase) {
        \App\Models\Phase::updateOrCreate(
                ['slug' => $phase['slug']],
                ['name' => $phase['name'], 'order' => $phase['order']]
            );
        }

        \App\Models\Phase::where('slug', 'harvest')->delete();

    }
}
