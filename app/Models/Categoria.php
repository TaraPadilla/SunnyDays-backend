<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\FormulaCalculatorService;

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
        'visible_combo',
        'estado'
    ];

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'visible_sum' => 'boolean',
            'visible_combo' => 'boolean',
            'estado' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Boot del modelo para agregar eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Eliminar en cascada cuando se borra una categoría
        static::deleting(function ($categoria) {
            // Primero eliminar todos los gastos asociados a las subcategorías de esta categoría
            $subcategorias = $categoria->subcategorias;
            foreach ($subcategorias as $subcategoria) {
                // Eliminar gastos de esta subcategoría
                $subcategoria->gastos()->delete();
            }
            
            // Luego eliminar todas las subcategorías de esta categoría
            $categoria->subcategorias()->delete();
        });
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
     * Calcula el subtotal delegando toda la lógica al FormulaCalculatorService
     */
    public function subtotal()
    {
        return FormulaCalculatorService::calculateSubtotal($this);
    }
}
