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
            'json_reservas' => [
                'required',
                'array',
                'min:1',
                new JsonReservasStructure()
            ],
            'json_gastos' => [
                'required',
                'array',
                'min:1'
            ],
        ];

        // Para actualización, hacer los campos opcionales
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['inmueble_id'] = 'sometimes|exists:inmuebles,id';
            $rules['fecha_corte'] = 'sometimes|date';
            $rules['json_reservas'] = [
                'sometimes',
                'array',
                'min:1',
                new JsonReservasStructure()
            ];
            $rules['json_gastos'] = [
                'sometimes',
                'array',
                'min:1'
            ];
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
            
            'json_reservas.required' => 'Campo json_reservas requerido.',
            'json_reservas.array' => 'Campo json_reservas debe ser arreglo.',
            'json_reservas.min' => 'Campo json_reservas requiere al menos un elemento.',
            'json_reservas.sometimes' => 'Campo json_reservas opcional.',
            
            'json_gastos.required' => 'Campo json_gastos requerido.',
            'json_gastos.array' => 'Campo json_gastos debe ser arreglo.',
            'json_gastos.min' => 'Campo json_gastos requiere al menos un elemento.',
            'json_gastos.sometimes' => 'Campo json_gastos opcional.',
        ];
    }
}
