<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceResource extends JsonResource
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
            'inmueble_id' => $this->inmueble_id,
            'inmueble' => $this->whenLoaded('inmueble', function () {
                return [
                    'id' => $this->inmueble->id,
                    'nombre' => $this->inmueble->nombre,
                    'direccion' => $this->inmueble->direccion,
                ];
            }),
            'fecha_corte' => $this->fecha_corte,
            'json_reservas' => $this->json_reservas,
            'json_gastos' => $this->json_gastos,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
