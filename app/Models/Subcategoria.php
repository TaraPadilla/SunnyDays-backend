<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\FormulaCalculatorService;
use App\Models\Gasto;
use Illuminate\Validation\ValidationException;

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
     * Boot del modelo para agregar validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($subcategoria) {
            $subcategoria->validateCategoriaAssociation();
        });

        // Eliminar en cascada cuando se borra una subcategoría
        static::deleting(function ($subcategoria) {
            // Eliminar todos los gastos asociados a esta subcategoría
            $subcategoria->gastos()->delete();
            
            // Eliminar el campo asociado a esta subcategoría si no está siendo usado por otras subcategorías
            if ($subcategoria->campo_id) {
                $campo = $subcategoria->campo;
                if ($campo && $campo->subcategorias()->count() <= 1) {
                    $campo->delete();
                }
            }
        });
    }

    /**
     * Valida que la categoría exista y sea válida
     */
    public function validateCategoriaAssociation()
    {
        if (!$this->categoria_id) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría es obligatoria.'
            ]);
        }

        $categoria = Categoria::find($this->categoria_id);
        
        if (!$categoria) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría seleccionada no existe.'
            ]);
        }

        if (!$categoria->estado) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría seleccionada está inactiva.'
            ]);
        }
    }

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
     * Calcula el subtotal delegando toda la lógica al FormulaCalculatorService
     */
    public function subtotal()
    {
        return FormulaCalculatorService::calculateSubtotal($this);
    }
}
