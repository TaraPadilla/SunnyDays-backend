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
        Schema::create('campos', function (Blueprint $table) {
            $table->id();
            
            // Identificador único para fórmulas (ej: CAT_01, SUB_TOTAL_OPERACION)
            $table->string('clave')->unique(); 
            
            // Nombre descriptivo (ej: Mantenimiento, Impuestos)
            $table->string('nombre'); 
            
            // Tipo de cálculo: 
            // SUM (Suma simple de gastos)
            // COMPUESTA (Operación entre claves ej: CAT_A + CAT_B)
            // MANUAL (Input del usuario como las Reservas)
            $table->enum('tipo_calculo', ['SUM', 'COMPUESTA', 'MANUAL'])->default('SUM');
            
            // Aquí se guarda la fórmula matemática o el ID de referencia si es necesario
            $table->text('formula')->nullable();
            
            // Estado de activación
            $table->boolean('estado')->default(true);

            // Campos de control de Laravel
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campos');
    }
};
