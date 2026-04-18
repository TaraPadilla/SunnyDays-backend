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
        Schema::create('inmuebles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->nullable(); // Para identificación interna
            $table->string('direccion')->nullable();
            $table->string('imagen')->nullable();  // Ruta del archivo
            $table->boolean('estado')->default(true);
            
            // Campos de control solicitados
            $table->timestamps(); // created_at y updated_at
            $table->softDeletes(); // deleted_at (para el borrado lógico solicitado) 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inmuebles');
    }
};
