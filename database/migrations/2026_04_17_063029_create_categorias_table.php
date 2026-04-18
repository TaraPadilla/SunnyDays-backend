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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            
            // Definimos el tipo para facilitar la lógica contable
            $table->enum('tipo', ['Ingreso', 'Egreso'])->default('Egreso');
            
            // Control visual y jerárquico para el reporte
            $table->boolean('visible_sum')->default(true);
            $table->integer('orden')->default(0);
            
            // Relación con la tabla campos (debe existir previamente)
            $table->foreignId('campo_id')
                  ->constrained('campos')
                  ->onDelete('cascade');

            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes(); // Para mantener historial
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
