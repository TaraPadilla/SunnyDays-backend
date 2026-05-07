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
            // Eliminar la foreign key incorrecta que apunta a categorías
            $table->dropForeign('whatsapp_sessions_categoria_gasto_id_foreign');
            
            // Agregar la foreign key correcta que apunta a subcategorías
            $table->foreign('categoria_gasto_id')
                  ->references('id')
                  ->on('subcategorias')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Eliminar la foreign key correcta
            $table->dropForeign('whatsapp_sessions_categoria_gasto_id_foreign');
            
            // Restaurar la foreign key incorrecta que apunta a categorías
            $table->foreign('categoria_gasto_id')
                  ->references('id')
                  ->on('categorias')
                  ->onDelete('cascade');
        });
    }
};
