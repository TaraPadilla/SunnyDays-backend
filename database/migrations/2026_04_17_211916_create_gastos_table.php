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
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            
            // Montos desglosados
            $table->decimal('monto_sin_iva', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0); // Monto fijo del IVA
            $table->decimal('monto_total', 12, 2); // Monto total
            
            $table->text('descripcion'); // No obligatorio para referencia
            
            // Relaciones
            $table->foreignId('inmueble_id')->constrained('inmuebles')->onDelete('cascade');
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('subcategoria_id')->constrained('subcategorias');

            // Metadata operativa
            $table->enum('tipo_pago', ['Efectivo', 'Transferencia', 'Tarjeta', 'Otro'])->default('Efectivo');
            $table->string('proveedor')->nullable();
            $table->string('numero_comprobante')->nullable();
            $table->text('observaciones')->nullable();

            // Auditoría y Control
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};

