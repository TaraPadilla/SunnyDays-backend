<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Gasto extends Model
{
    use HasFactory, SoftDeletes;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'fecha',
        'monto_sin_iva',
        'iva',
        'monto_total',
        'tipo_soporte',
        'descripcion',
        'inmueble_id',
        'categoria_id',
        'subcategoria_id',
        'tipo_pago',
        'proveedor',
        'numero_comprobante',
        'observaciones'
    ];

    /**
     * Boot del modelo para agregar validaciones
     */
    protected static function boot()
    {
        parent::boot();

        // Validar antes de guardar
        static::saving(function ($gasto) {
            $gasto->validateCategoriaSubcategoriaAssociation();
        });
    }

    /**
     * Valida que la subcategoría pertenezca a la categoría
     */
    public function validateCategoriaSubcategoriaAssociation()
    {
        if ($this->categoria_id && $this->subcategoria_id) {
            $subcategoria = Subcategoria::find($this->subcategoria_id);
            
            if (!$subcategoria || $subcategoria->categoria_id != $this->categoria_id) {
                throw ValidationException::withMessages([
                    'subcategoria_id' => 'La subcategoría seleccionada no pertenece a la categoría seleccionada.'
                ]);
            }
        }
    }

    /**
     * Definición de tipos de datos (Casts)
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_sin_iva' => 'decimal:2',
            'iva' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relación con Inmueble
     */
    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }

    /**
     * Relación con Categoría
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Relación con Subcategoría
     */
    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class);
    }
}
