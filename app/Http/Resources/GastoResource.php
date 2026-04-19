<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GastoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha,
            'monto_sin_iva' => $this->monto_sin_iva,
            'iva' => $this->iva,
            'monto_total' => $this->monto_total,
            'tipo_soporte' => $this->tipo_soporte,
            'descripcion' => $this->descripcion,
            'tipo_pago' => $this->tipo_pago,
            'proveedor' => $this->proveedor,
            'numero_comprobante' => $this->numero_comprobante,
            'observaciones' => $this->observaciones,
            'inmueble' => [
                'id' => $this->inmueble->id,
                'nombre' => $this->inmueble->nombre,
                'direccion' => $this->inmueble->direccion,
            ],
            'categoria' => [
                'id' => $this->categoria->id,
                'nombre' => $this->categoria->nombre,
                'tipo' => $this->categoria->tipo,
                'campo' => $this->categoria->campo ? [
                    'id' => $this->categoria->campo->id,
                    'clave' => $this->categoria->campo->clave,
                    'nombre' => $this->categoria->campo->nombre,
                    'tipo_calculo' => $this->categoria->campo->tipo_calculo,
                ] : null,
            ],
            'subcategoria' => [
                'id' => $this->subcategoria->id,
                'nombre' => $this->subcategoria->nombre,
                'campo' => $this->subcategoria->campo ? [
                    'id' => $this->subcategoria->campo->id,
                    'clave' => $this->subcategoria->campo->clave,
                    'nombre' => $this->subcategoria->campo->nombre,
                    'tipo_calculo' => $this->subcategoria->campo->tipo_calculo,
                ] : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
