<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Gasto;

class Subcategoria extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'categoria_id',
        'campo_id',
        'visible_combo',
        'orden',
        'estado'
    ];

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'visible_combo' => 'boolean',
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relación con Categoria
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Relación con Campo
     */
    public function campo(): BelongsTo
    {
        return $this->belongsTo(Campo::class);
    }

    /**
     * Relación con Gastos
     */
    public function gastos()
    {
        return $this->hasMany(Gasto::class);
    }

    /**
     * Calcula el subtotal de todos los gastos de esta subcategoría
     * solo si el campo relacionado tiene tipo_calculo = 'SUM'.
     * Temporalmente, si es 'COMPUESTA', devuelve 2000.
     */
    public function subtotal()
    {
        $campo = $this->campo;

        if (!$campo) {
            return 0;
        } else {
            if ($campo->tipo_calculo === 'SUM') {
                return $this->gastos()->sum('monto_total');
            } elseif ($campo->tipo_calculo === 'COMPUESTA') {
                return 2000;
            } else {
                return 0;
            }
        }
    }
}
