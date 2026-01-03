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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_batch_id')->nullable()->constrained('batches')->nullOnDelete()->after('id');
            $table->foreignId('strain_id')->constrained()->cascadeOnDelete(); 
            $table->string('code')->unique(); 
            $table->decimal('weigth_dry', 8, 2); 
            $table->date('inoculation_date'); 
            $table->integer('quantity')->default(1); 
            $table->decimal('bag_weight', 8, 3)->nullable();
            $table->string('type')->default('bulk');
            $table->string('grain_type')->nullable(); 
            $table->string('container_type')->nullable();
            $table->string('status')->nullable(); 
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
