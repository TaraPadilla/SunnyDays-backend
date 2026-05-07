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
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            
            // Identificación del usuario de WhatsApp
            $table->string('wa_id')->unique()->comment('Número de WhatsApp del usuario');
            
            // Estado actual del flujo de registro de gastos
            $table->enum('estado_actual', [
                'STARTED',
                'SELECTING_DATE',
                'SELECTING_PROPERTY',
                'SELECTING_CATEGORY',
                'SELECTING_SUBCATEGORY',
                'SELECTING_AMOUNT_WITHOUT_VAT',
                'SELECTING_VAT',
                'SELECTING_TOTAL_AMOUNT',
                'SELECTING_OBSERVATIONS',
                'COMPLETED'
            ])->default('STARTED')->index();
            
            // Datos temporales del flujo de registro de gastos
            $table->date('fecha_gasto')->nullable()->comment('Fecha del gasto a registrar');
            $table->foreignId('inmueble_id')->nullable()->constrained('inmuebles')->onDelete('cascade')->comment('Propiedad seleccionada');
            $table->foreignId('tipo_categoria_id')->nullable()->constrained('categorias')->onDelete('cascade')->comment('Tipo de categoría seleccionado');
            $table->foreignId('categoria_gasto_id')->nullable()->constrained('categorias')->onDelete('cascade')->comment('Categoría de gasto seleccionada');
            
            // Montos del gasto
            $table->decimal('monto_sin_iva', 12, 2)->nullable()->comment('Monto sin IVA');
            $table->decimal('iva', 12, 2)->nullable()->comment('Monto del IVA');
            $table->decimal('monto_total', 12, 2)->nullable()->comment('Monto total del gasto');
            
            // Campos adicionales
            $table->text('observaciones')->nullable()->comment('Observaciones del gasto');
            
            // Control del flujo conversacional
            $table->timestamp('ultimo_mensaje_at')->nullable()->comment('Timestamp del último mensaje procesado');
            $table->timestamp('completed_at')->nullable()->comment('Timestamp de finalización del flujo');
            
            // Timestamps y soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para optimización
            $table->index(['wa_id', 'estado_actual']);
            $table->index('fecha_gasto');
            $table->index('ultimo_mensaje_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
