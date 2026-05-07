<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppSession extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wa_id',
        'estado_actual',
        'fecha_gasto',
        'inmueble_id',
        'tipo_categoria_id',
        'categoria_gasto_id',
        'monto_sin_iva',
        'iva',
        'monto_total',
        'observaciones',
        'ultimo_mensaje_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_gasto' => 'date',
        'monto_sin_iva' => 'decimal:2',
        'iva' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'ultimo_mensaje_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the property associated with the WhatsApp session.
     */
    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }

    /**
     * Get the category type associated with the WhatsApp session.
     */
    public function tipoCategoria()
    {
        return $this->belongsTo(Categoria::class, 'tipo_categoria_id');
    }

    /**
     * Get the expense category associated with the WhatsApp session.
     */
    public function categoriaGasto()
    {
        return $this->belongsTo(Categoria::class, 'categoria_gasto_id');
    }
}
