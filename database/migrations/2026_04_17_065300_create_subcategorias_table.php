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
        Schema::create('subcategorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            
            // Relación jerárquica con Categorías (Nivel 1)
            $table->foreignId('categoria_id')
                  ->constrained('categorias')
                  ->onDelete('cascade');

            // Relación con el motor de cálculo
            $table->foreignId('campo_id')
                  ->constrained('campos')
                  ->onDelete('cascade');
            
            // Ajuste solicitado: Control para el formulario de registro
            $table->boolean('visible_combo')->default(true);
            
            $table->integer('orden')->default(0);
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subcategorias');
    }
};
