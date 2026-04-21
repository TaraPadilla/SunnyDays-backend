<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\JsonReservasStructure;

class BalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'inmueble_id' => 'required|exists:inmuebles,id',
            'fecha_corte' => 'required|date',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
            'json_reservas' => 'required|array',
            'json_reservas.reservas' => [
                'required',
                'array',
                'min:1',
                new JsonReservasStructure()
            ],
            'json_reservas.total' => 'required|numeric|min:0',
            'json_gastos' => 'required|array',
            'json_gastos.categorias' => 'required|array|min:1',
            'json_gastos.categorias.*' => 'array',
            'json_gastos.categorias.*.categoria' => 'required|string',
            'json_gastos.categorias.*.subcategorias' => 'required|array|min:1',
            'json_gastos.categorias.*.subcategorias.*' => 'array',
            'json_gastos.categorias.*.subcategorias.*.subcategoria' => 'required|string',
            'json_gastos.categorias.*.subcategorias.*.monto' => 'required|numeric|min:0',
            'json_gastos.categorias.*.subtotal' => 'sometimes|numeric|min:0',
            'json_gastos.total' => 'required|numeric|min:0',
        ];

        // Para actualización, hacer los campos opcionales
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['inmueble_id'] = 'sometimes|exists:inmuebles,id';
            $rules['fecha_corte'] = 'sometimes|date';
            $rules['fecha_desde'] = 'sometimes|date';
            $rules['fecha_hasta'] = 'sometimes|date';
            $rules['json_reservas'] = 'sometimes|array';
            $rules['json_reservas.reservas'] = [
                'sometimes',
                'array',
                'min:1',
                new JsonReservasStructure()
            ];
            $rules['json_reservas.total'] = 'sometimes|numeric|min:0';
            $rules['json_gastos'] = 'sometimes|array';
            $rules['json_gastos.categorias'] = 'sometimes|array|min:1';
            $rules['json_gastos.categorias.*'] = 'sometimes|array';
            $rules['json_gastos.categorias.*.categoria'] = 'sometimes|string';
            $rules['json_gastos.categorias.*.subcategorias'] = 'sometimes|array|min:1';
            $rules['json_gastos.categorias.*.subcategorias.*'] = 'sometimes|array';
            $rules['json_gastos.categorias.*.subcategorias.*.subcategoria'] = 'sometimes|string';
            $rules['json_gastos.categorias.*.subcategorias.*.monto'] = 'sometimes|numeric|min:0';
            $rules['json_gastos.categorias.*.subtotal'] = 'sometimes|numeric|min:0';
            $rules['json_gastos.total'] = 'sometimes|numeric|min:0';
        }

        return $rules;
    }

    /**
     * Get the custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'inmueble_id.required' => 'Campo inmueble_id requerido.',
            'inmueble_id.exists' => 'Inmueble seleccionado no existe.',
            'inmueble_id.sometimes' => 'Campo inmueble_id opcional.',
            
            'fecha_corte.required' => 'Campo fecha_corte requerido.',
            'fecha_corte.date' => 'Campo fecha_corte debe ser fecha válida.',
            'fecha_corte.sometimes' => 'Campo fecha_corte opcional.',
            
            'fecha_desde.required' => 'Campo fecha_desde requerido.',
            'fecha_desde.date' => 'Campo fecha_desde debe ser fecha válida.',
            'fecha_desde.sometimes' => 'Campo fecha_desde opcional.',
            
            'fecha_hasta.required' => 'Campo fecha_hasta requerido.',
            'fecha_hasta.date' => 'Campo fecha_hasta debe ser fecha válida.',
            'fecha_hasta.sometimes' => 'Campo fecha_hasta opcional.',
            
            'json_reservas.required' => 'Campo json_reservas requerido.',
            'json_reservas.array' => 'Campo json_reservas debe ser un objeto.',
            'json_reservas.sometimes' => 'Campo json_reservas opcional.',
            
            'json_reservas.reservas.required' => 'Campo json_reservas.reservas requerido.',
            'json_reservas.reservas.array' => 'Campo json_reservas.reservas debe ser un arreglo.',
            'json_reservas.reservas.min' => 'Campo json_reservas.reservas requiere al menos un elemento.',
            
            'json_reservas.total.required' => 'Campo json_reservas.total requerido.',
            'json_reservas.total.numeric' => 'Campo json_reservas.total debe ser un número.',
            
            'json_gastos.required' => 'Campo json_gastos requerido.',
            'json_gastos.array' => 'Campo json_gastos debe ser un objeto.',
            'json_gastos.sometimes' => 'Campo json_gastos opcional.',
            
            'json_gastos.categorias.required' => 'Campo json_gastos.categorias requerido.',
            'json_gastos.categorias.array' => 'Campo json_gastos.categorias debe ser un arreglo.',
            'json_gastos.categorias.min' => 'Campo json_gastos.categorias requiere al menos un elemento.',
            
            'json_gastos.categorias.*.categoria.required' => 'Campo categoria requerido en categorías.',
            'json_gastos.categorias.*.subcategorias.required' => 'Campo subcategorias requerido en categorías.',
            'json_gastos.categorias.*.subcategorias.array' => 'Campo subcategorias debe ser un arreglo.',
            'json_gastos.categorias.*.subcategorias.min' => 'Campo subcategorias requiere al menos un elemento.',
            
            'json_gastos.categorias.*.subcategorias.*.subcategoria.required' => 'Campo subcategoria requerido en subcategorías.',
            'json_gastos.categorias.*.subcategorias.*.monto.required' => 'Campo monto requerido en subcategorías.',
            'json_gastos.categorias.*.subcategorias.*.monto.numeric' => 'Campo monto debe ser un número.',
            
            'json_gastos.categorias.*.subtotal.numeric' => 'Campo subtotal debe ser un número.',
            
            'json_gastos.total.required' => 'Campo json_gastos.total requerido.',
            'json_gastos.total.numeric' => 'Campo json_gastos.total debe ser un número.',
        ];
    }
}
