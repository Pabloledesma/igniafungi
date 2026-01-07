<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create(['name' => 'Pablo', 'email' => 'gerencia@igniafungi.com', 'password' => Hash::make('password')]);
        User::factory()->create(['name' => 'Alexa', 'email' => 'comercial@igniafungi.com', 'password' => Hash::make('password')]);
    }
}
