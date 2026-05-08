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
            // Actualizar el enum para agregar el estado SELECTING_TOTAL_AMOUNT_MANUAL
            $table->enum('estado_actual', [
                'STARTED',
                'SELECTING_DATE',
                'SELECTING_PROPERTY',
                'SELECTING_CATEGORY',
                'SELECTING_SUBCATEGORY',
                'SELECTING_AMOUNT_WITHOUT_VAT',
                'SELECTING_VAT',
                'SELECTING_TOTAL_AMOUNT',
                'SELECTING_TOTAL_AMOUNT_MANUAL',
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
            // Restaurar el enum original sin SELECTING_TOTAL_AMOUNT_MANUAL
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
};
