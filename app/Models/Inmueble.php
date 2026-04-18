<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inmueble extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre', 
        'codigo', 
        'direccion', 
        'imagen', 
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

    /**
     * Relación con Gastos (Definida en el análisis de arquitectura)
     * Un Inmueble tiene muchos Gastos.
     */
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }
}