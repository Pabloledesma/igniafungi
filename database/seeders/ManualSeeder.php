<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ManualSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tabla (Opcional, pero bueno para seeders idempotentes)
        // \App\Models\Manual::truncate(); // Comentado para evitar borrar si ya existen

        $user = \App\Models\User::first() ?? \App\Models\User::factory()->create();

        // 1. Manual de Usuario
        $userGuidePath = base_path('USER_GUIDE.md');
        if (file_exists($userGuidePath)) {
            \App\Models\Manual::updateOrCreate(
                ['slug' => 'manual-de-operacion'],
                [
                    'title' => 'Manual de Operación',
                    'category' => 'Usuario',
                    'icon' => '🍄',
                    'content' => file_get_contents($userGuidePath),
                    'is_published' => true,
                    'user_id' => $user->id,
                ]
            );
        }

        // 2. Reglas de Negocio
        $rulesPath = base_path('SYSTEM_RULES.md');
        if (file_exists($rulesPath)) {
            \App\Models\Manual::updateOrCreate(
                ['slug' => 'reglas-de-negocio'],
                [
                    'title' => 'Reglas de Negocio',
                    'category' => 'Negocio',
                    'icon' => '⚖️',
                    'content' => file_get_contents($rulesPath),
                    'is_published' => true,
                    'user_id' => $user->id,
                ]
            );
        }
    }
}
