<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar registros antiguos
        DB::table('batch_losses')
            ->where('reason', 'contamination') // English lowercase key
            ->update(['reason' => 'Contaminación']);

        DB::table('batch_losses')
            ->where('reason', 'Contamination') // English Capitalized
            ->update(['reason' => 'Contaminación']);

        // Standardize others if necessary
        DB::table('batch_losses')
            ->where('reason', 'pest')
            ->update(['reason' => 'Plagas']); // Assuming simplistic mapping or keep as is if code handled it

        // The requirement specifically mentioned 'Contamination' -> 'Contaminación'
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert logic necessary or safe here as it merges data
    }
};
