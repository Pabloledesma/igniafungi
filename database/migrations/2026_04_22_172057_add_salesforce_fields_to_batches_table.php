<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->float('sf_eficiencia_biologica')->nullable()->after('expected_yield');
            $table->float('sf_total_cosechado_kg')->nullable()->after('sf_eficiencia_biologica');
            $table->integer('sf_cantidad_cosechas')->nullable()->after('sf_total_cosechado_kg');
            $table->boolean('sf_archivado')->default(false)->after('sf_cantidad_cosechas');
            $table->timestamp('sf_synced_at')->nullable()->after('sf_archivado');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn([
                'sf_eficiencia_biologica',
                'sf_total_cosechado_kg',
                'sf_cantidad_cosechas',
                'sf_archivado',
                'sf_synced_at',
            ]);
        });
    }
};
