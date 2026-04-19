<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear un trigger para validar que la subcategoría pertenezca a la categoría
        DB::unprepared("
            CREATE TRIGGER check_categoria_subcategoria_before_insert
            BEFORE INSERT ON gastos
            FOR EACH ROW
            BEGIN
                DECLARE subcategoria_categoria_id INT;
                
                SELECT categoria_id INTO subcategoria_categoria_id
                FROM subcategorias
                WHERE id = NEW.subcategoria_id;
                
                IF subcategoria_categoria_id IS NULL OR subcategoria_categoria_id != NEW.categoria_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'La subcategoría seleccionada no pertenece a la categoría seleccionada.';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER check_categoria_subcategoria_before_update
            BEFORE UPDATE ON gastos
            FOR EACH ROW
            BEGIN
                DECLARE subcategoria_categoria_id INT;
                
                SELECT categoria_id INTO subcategoria_categoria_id
                FROM subcategorias
                WHERE id = NEW.subcategoria_id;
                
                IF subcategoria_categoria_id IS NULL OR subcategoria_categoria_id != NEW.categoria_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'La subcategoría seleccionada no pertenece a la categoría seleccionada.';
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar los triggers
        DB::unprepared("DROP TRIGGER IF EXISTS check_categoria_subcategoria_before_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS check_categoria_subcategoria_before_update");
    }
};
