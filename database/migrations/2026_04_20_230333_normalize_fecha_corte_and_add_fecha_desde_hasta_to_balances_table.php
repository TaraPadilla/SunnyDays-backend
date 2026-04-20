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
        Schema::table('balances', function (Blueprint $table) {
            // Add new fields for date range
            $table->date('fecha_desde')->nullable()->after('fecha_corte');
            $table->date('fecha_hasta')->nullable()->after('fecha_desde');
            
            // Make fecha_corte nullable to allow for date range
            $table->date('fecha_corte')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            // Remove the new fields
            $table->dropColumn('fecha_hasta');
            $table->dropColumn('fecha_desde');
            
            // Make fecha_corte not nullable again
            $table->date('fecha_corte')->nullable(false)->change();
        });
    }
};
