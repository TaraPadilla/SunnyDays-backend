<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SubcategoriaResource;

class CategoriaResource extends JsonResource
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
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'visible_sum' => $this->visible_sum,
            'visible_combo' => $this->visible_combo,
            'orden' => $this->orden,
            'campo_id' => $this->campo_id,
            'campo' => $this->when($this->relationLoaded('campo'), function () {
                return new CampoResource($this->campo);
            }),
            'subcategorias' => $this->when($this->relationLoaded('subcategorias'), function () {
                return SubcategoriaResource::collection($this->subcategorias);
            }),
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
