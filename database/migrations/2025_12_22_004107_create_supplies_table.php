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
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            
            $table->string('name'); // Ej: "Arroz Integral"
            $table->string('category'); // Ej: "grain", "substrate", "consumable", "equipment"
            
            // Control de Inventario
            $table->decimal('quantity', 10, 2)->default(0); // Cuánto tengo (Ej: 50.5)
            $table->string('unit'); // Unidad de medida (Ej: "kg", "litros", "unidades")
            
            // Alertas y Costos
            $table->decimal('min_stock', 10, 2)->default(0); // Alerta si baja de esto
            $table->decimal('cost_per_unit', 10, 2)->nullable(); // Para calcular costos de producción futuros
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
