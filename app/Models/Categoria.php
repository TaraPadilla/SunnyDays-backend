<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Categoria extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'tipo',
        'visible_sum',
        'orden',
        'campo_id',
        'estado'
    ];

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'visible_sum' => 'boolean',
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relación con Campo
     */
    public function campo(): BelongsTo
    {
        return $this->belongsTo(Campo::class);
    }
}
