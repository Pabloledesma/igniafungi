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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary'); // Breve descripción para la lista
            $table->longText('content'); // El cuerpo del post
            $table->string('image')->nullable(); // Imagen de portada
            $table->boolean('is_published')->default(false);
            $table->foreignId('user_id')->constrained(); // Autor
            $table->string('meta_description')->nullable(); // Para Google SEO
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
