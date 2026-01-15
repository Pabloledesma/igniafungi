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
            RecipeSeeder::class,
        ]);

        $this->call([
            BatchSeeder::class,
        ]);
    }

}
