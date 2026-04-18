<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubcategoriaResource extends JsonResource
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
            'categoria_id' => $this->categoria_id,
            'categoria' => $this->when($this->relationLoaded('categoria'), function () {
                return new CategoriaResource($this->categoria);
            }),
            'campo_id' => $this->campo_id,
            'campo' => $this->when($this->relationLoaded('campo'), function () {
                return new CampoResource($this->campo);
            }),
            'visible_combo' => $this->visible_combo,
            'orden' => $this->orden,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
