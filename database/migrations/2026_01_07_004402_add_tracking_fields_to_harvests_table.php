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
        Schema::table('harvests', function (Blueprint $table) {
            // 1. Fecha real de la cosecha (independiente del created_at)
            $table->timestamp('harvest_date')->nullable()->after('weight');
            
            // 2. Notas u observaciones del operario
            $table->text('notes')->nullable()->after('harvest_date');
            
            // 3. Relación con la fase en la que se encontraba el lote
            $table->foreignId('phase_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('set null')
                  ->after('batch_id');
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('harvests', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropColumn(['harvest_date', 'notes', 'phase_id']);
        });
    }
};
