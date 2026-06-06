<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\SubcategoriaResource;

class CampoResource extends JsonResource
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
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'tipo_calculo' => $this->tipo_calculo,
            'formula' => $this->formula,
            'estado' => $this->estado,
            'tipo_resultado' => $this->tipo_resultado,
            'categorias' => CategoriaResource::collection($this->whenLoaded('categorias')),
            'subcategorias' => SubcategoriaResource::collection($this->whenLoaded('subcategorias')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
