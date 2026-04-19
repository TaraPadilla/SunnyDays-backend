<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Boot del modelo para manejar cambios en tipo_calculo
     */
    protected static function boot()
    {
        parent::boot();

        // Manejar cambios antes de guardar
        static::saving(function ($campo) {
            $campo->handleTipoCalculoChange();
        });
    }

    /**
     * Maneja los cambios en tipo_calculo
     */
    public function handleTipoCalculoChange()
    {
        // Si el tipo es SUM, la fórmula debe ser NULL
        if ($this->tipo_calculo === 'SUM') {
            $this->formula = null;
        }
        
        // Si el tipo cambia a COMPUESTA y no hay fórmula, se puede dejar vacía
        // para que el usuario la ingrese posteriormente
    }

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
     * Relación con Categorías
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    /**
     * Relación con Subcategorías
     */
    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class);
    }
}
