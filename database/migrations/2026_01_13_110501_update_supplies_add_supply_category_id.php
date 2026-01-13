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
        Schema::table('supplies', function (Blueprint $table) {
            $table->foreignId('supply_category_id')->nullable()->constrained('supply_categories')->onDelete('set null');
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->dropForeign(['supply_category_id']);
            $table->dropColumn('supply_category_id');
            $table->string('category')->nullable();
        });
    }
};
