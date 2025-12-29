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
        Schema::create('strains', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: "Blue Oyster 301"
            $table->string('type')->nullable(); // Ej: "Pleurotus", "Shiitake"
            $table->integer('incubation_days')->default(15); // Para predecir cuándo fructifica
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strains');
    }
};
