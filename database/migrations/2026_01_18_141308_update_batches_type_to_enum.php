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
        // 1. Sanitize Data: Convert invalid types to 'bulk'
        DB::table('batches')
            ->whereNotIn('type', ['grain', 'bulk'])
            ->update(['type' => 'bulk']);

        // 2. Modify Column to ENUM
        Schema::table('batches', function (Blueprint $table) {
            $table->enum('type', ['grain', 'bulk'])->default('bulk')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('type')->default('bulk')->change();
        });
    }
};
