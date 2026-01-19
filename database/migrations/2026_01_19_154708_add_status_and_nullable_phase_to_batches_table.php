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
            // $table->string('status')->default('active')->after('phase_id'); // Column already exists in create_batches_table
            // We just ensure phase_id is nullable. Modifying status default to 'active' might need dbal, 
            // but we are handling it in Model Observer.

            // Check if we can change it to default active? 
            // For now, let's just create the column if it doesn't exist (safety) or ignore.
            if (!Schema::hasColumn('batches', 'status')) {
                $table->string('status')->default('active')->after('phase_id');
            } else {
                // If it exists, we might want to ensure it has default 'active'
                // $table->string('status')->default('active')->change(); 
                // Leaving commented to avoid SQLite issues if dbal is missing.
            }
            // Change phase_id to nullable
            $table->unsignedBigInteger('phase_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // $table->dropColumn('status'); // Do not drop, it belongs to original schema
            // $table->unsignedBigInteger('phase_id')->nullable(false)->change(); // phase_id doesn't exist
        });
    }
};
