<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $campos = [
            ['clave' => 'AIRBNB_INGRESOS_BRUTOS', 'tipo_resultado' => 'CURRENCY'],
            ['clave' => 'AIRBNB_NOCHES', 'tipo_resultado' => 'ENTERO'],
            ['clave' => 'AIRBNB_SEGURO', 'tipo_resultado' => 'ENTERO'],
            ['clave' => 'BOOKING_INGRESOS_BRUTOS', 'tipo_resultado' => 'CURRENCY'],
            ['clave' => 'BOOKING_NOCHES', 'tipo_resultado' => 'ENTERO'],
            ['clave' => 'BOOKING_SEGURO', 'tipo_resultado' => 'ENTERO'],
            ['clave' => 'DIRECTOS_INGRESOS_BRUTOS', 'tipo_resultado' => 'CURRENCY'],
            ['clave' => 'DIRECTOS_NOCHES', 'tipo_resultado' => 'ENTERO'],
            ['clave' => 'DIRECTOS_SEGURO', 'tipo_resultado' => 'ENTERO'],
        ];

        foreach ($campos as $campo) {
            DB::table('campos')->updateOrInsert(
                ['clave' => $campo['clave']],
                [
                    'nombre' => $campo['clave'],
                    'tipo_calculo' => 'MANUAL',
                    'tipo_resultado' => $campo['tipo_resultado'],
                    'formula' => null,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('campos')
            ->whereIn('clave', [
                'AIRBNB_INGRESOS_BRUTOS',
                'AIRBNB_NOCHES',
                'AIRBNB_SEGURO',
                'BOOKING_INGRESOS_BRUTOS',
                'BOOKING_NOCHES',
                'BOOKING_SEGURO',
                'DIRECTOS_INGRESOS_BRUTOS',
                'DIRECTOS_NOCHES',
                'DIRECTOS_SEGURO',
            ])
            ->delete();
    }
};
