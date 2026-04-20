<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Balance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inmueble_id',
        'fecha_corte',
        'fecha_desde',
        'fecha_hasta',
        'json_reservas',
        'json_gastos',
    ];

    protected $casts = [
        'fecha_corte' => 'date',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'json_reservas' => 'array',
        'json_gastos' => 'array',
    ];

    /**
     * Get the inmueble that owns the balance.
     */
    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }
}
