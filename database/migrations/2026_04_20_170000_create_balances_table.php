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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inmueble_id')->constrained('inmuebles')->onDelete('cascade');
            $table->date('fecha_corte');
            $table->json('json_reservas');
            $table->json('json_gastos');
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['inmueble_id', 'fecha_corte']);
            $table->unique(['inmueble_id', 'fecha_corte'], 'balances_inmueble_fecha_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
