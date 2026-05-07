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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Quitar índice unique de wa_id
            $table->dropUnique('whatsapp_sessions_wa_id_unique');
            
            // Agregar índice normal a wa_id
            $table->index('wa_id');
            
            // Modificar el enum para agregar el estado CANCELLED
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
                'COMPLETED',
                'CANCELLED'
            ])->default('STARTED')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Quitar índice normal de wa_id
            $table->dropIndex('whatsapp_sessions_wa_id_index');
            
            // Restaurar índice unique a wa_id
            $table->unique('wa_id');
            
            // Restaurar el enum original sin CANCELLED
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
            ])->default('STARTED')->change();
        });
    }
};
