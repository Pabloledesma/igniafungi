<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            // Hacemos que la fecha de inoculación sea opcional
            $table->date('inoculation_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->date('inoculation_date')->nullable(false)->change();
        });
    }
};
