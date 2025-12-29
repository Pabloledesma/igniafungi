<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipe_supply', function (Blueprint $table) {
            $table->id();
            // Une con la tabla de recetas
            $table->foreignId('recipe_id')->constrained()->onDelete('cascade');
            // Une con la tabla de insumos (Supplies)
            $table->foreignId('supply_id')->constrained()->onDelete('cascade');
            // Cantidad específica que usa esta receta
            $table->decimal('quantity', 10, 2); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_supply');
    }
};
