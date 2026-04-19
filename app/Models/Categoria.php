<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Relación con Subcategorias
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class);
    }

    /**
     * Relación con Gastos
     */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

    /**
     * Calcula el subtotal de todos los gastos de esta categoría
     * solo si el campo relacionado tiene tipo_calculo = 'SUM'.
     * Si es SUM, itera recursivamente sobre las subcategorías y suma sus subtotales.
     * Temporalmente, si es 'COMPUESTA', devuelve 2000.
     */
    public function subtotal()
    {
        $campo = $this->campo;

        if (!$campo) {
            return 0;
        }

        if ($campo->tipo_calculo === 'SUM') {
            $subtotal = 0;
            
            // Recorrer cada subcategoría hija y sumar su subtotal
            foreach ($this->subcategorias as $subcategoria) {
                $subtotal += $subcategoria->subtotal();
            }
            
            return $subtotal;
        } elseif ($campo->tipo_calculo === 'COMPUESTA') {
            return 2000;
        } else {
            return 0;
        }
    }
}
