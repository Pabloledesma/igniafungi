<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // $table->string('status')->default('active')->after('type'); // "phase_id" column does not exist!

            if (!Schema::hasColumn('batches', 'status')) {
                // If status doesn't exist, we add it. 
                // Note: We cannot use 'after' phase_id because it doesn't exist. Using 'after' type or letting it append.
                $table->string('status')->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // We do typically not drop columns in down() if they might have data, 
            // but if we added it, we could drop it. 
            // However, since we check Schema::hasColumn, we assume it might exist from previous migrations.
            // Leaving empty for safety.
        });
    }
};
