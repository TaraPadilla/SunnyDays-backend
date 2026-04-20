<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soporte_gastos', function (Blueprint $table) {
            $table->id();
            
            // Relación con el gasto (Un gasto -> N soportes)
            $table->foreignId('gasto_id')
                  ->constrained('gastos')
                  ->onDelete('cascade'); // Si se elimina el gasto, se eliminan sus soportes
            
            // Datos del archivo
            $table->string('archivo'); // Almacena la ruta (path) o URL del archivo
            $table->string('nombre_original')->nullable(); // Opcional: para mostrar el nombre real al descargar
            $table->string('mime_type')->nullable(); // Opcional: para identificar si es image/jpeg, application/pdf, etc.

            // Campos de Control de Laravel solicitados
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soporte_gastos');
    }
};
