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
        Schema::table('orders', function (Blueprint $table) {
            // Usamos string con índice único para búsquedas rápidas y evitar duplicidad
            $table->string('reference')->unique()->after('id')->comment('Referencia única para pasarelas de pago');
            
            // Opcional: Guardar el ID de transacción de Bold para auditoría e idempotencia
            $table->string('bold_transaction_id')->nullable()->unique()->after('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['reference', 'bold_transaction_id']);
        });
    }
};
