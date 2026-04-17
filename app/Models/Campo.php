<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campo extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'clave',
        'nombre',
        'tipo_calculo',
        'formula',
        'estado'
    ];

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
