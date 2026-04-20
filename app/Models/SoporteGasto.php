<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoporteGasto extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'gasto_id',
        'archivo',
        'nombre_original',
        'mime_type'
    ];

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relación con Gasto
     */
    public function gasto()
    {
        return $this->belongsTo(Gasto::class);
    }
}
