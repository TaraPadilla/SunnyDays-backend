<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class JsonReservasStructure implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        Log::info('[JsonReservasStructure] validate: iniciando', [
            'attribute' => $attribute,
            'value' => $value,
            'value_type' => gettype($value),
            'is_array' => is_array($value),
            'is_empty' => empty($value),
            'count' => is_array($value) ? count($value) : 'not_array'
        ]);

        // Verificar que sea un arreglo
        if (!is_array($value)) {
            Log::error('[JsonReservasStructure] validate: no es array', ['value' => $value]);
            $fail('El campo debe ser un arreglo de objetos.');
            return;
        }

        // Permitir arrays vacíos (para balances sin reservas)
        if (empty($value)) {
            Log::info('[JsonReservasStructure] validate: array vacío, permitiendo continuar');
            return; // Permitir arrays vacíos
        }

        foreach ($value as $index => $row) {
            // Verificar que cada fila sea un objeto/arreglo
            if (!is_array($row)) {
                $fail("Fila {$index}: debe ser un objeto con las propiedades definidas.");
                return;
            }

            // Verificar que no haya campos adicionales
            $allowedFields = ['canal', 'desde', 'hasta', 'ingresos_brutos', 'noches', 'seguro'];
            $extraFields = array_diff(array_keys($row), $allowedFields);
            if (!empty($extraFields)) {
                $fail("Fila {$index}: campos no permitidos (" . implode(', ', $extraFields) . ")");
                return;
            }

            // Validar campo 'canal'
            if (!isset($row['canal'])) {
                $fail("Fila {$index}: campo canal requerido.");
                return;
            }

            if (!is_string($row['canal'])) {
                $fail("Fila {$index}: campo canal debe ser texto.");
                return;
            }

            if (trim($row['canal']) === '') {
                $fail("Fila {$index}: campo canal no puede estar vacío.");
                return;
            }

            // Validar campo 'desde'
            if (!isset($row['desde'])) {
                $fail("Fila {$index}: campo desde requerido.");
                return;
            }

            if (!$this->isValidDate($row['desde'])) {
                $fail("Fila {$index}: campo desde debe ser fecha válida (YYYY-MM-DD).");
                return;
            }

            // Validar campo 'hasta'
            if (!isset($row['hasta'])) {
                $fail("Fila {$index}: campo hasta requerido.");
                return;
            }

            if (!$this->isValidDate($row['hasta'])) {
                $fail("Fila {$index}: campo hasta debe ser fecha válida (YYYY-MM-DD).");
                return;
            }

            // Validar que hasta sea mayor o igual a desde
            if (strtotime($row['hasta']) < strtotime($row['desde'])) {
                $fail("Fila {$index}: campo hasta debe ser mayor o igual a desde.");
                return;
            }

            // Validar campo 'ingresos_brutos'
            if (!isset($row['ingresos_brutos'])) {
                $fail("Fila {$index}: campo ingresos_brutos requerido.");
                return;
            }

            if (!is_numeric($row['ingresos_brutos']) || $row['ingresos_brutos'] < 0) {
                $fail("Fila {$index}: campo ingresos_brutos debe ser número mayor o igual a 0.");
                return;
            }

            // Validar campo 'noches' (solo enteros, sin decimales)
            if (!isset($row['noches'])) {
                $fail("Fila {$index}: campo noches requerido.");
                return;
            }

            if (!is_numeric($row['noches'])) {
                $fail("Fila {$index}: campo noches debe ser número.");
                return;
            }

            // Verificar que sea entero sin decimales
            $nochesFloat = (float)$row['noches'];
            $nochesInt = (int)$row['noches'];
            if ($nochesFloat != $nochesInt) {
                $fail("Fila {$index}: campo noches debe ser entero sin decimales.");
                return;
            }

            if ($nochesInt <= 0) {
                $fail("Fila {$index}: campo noches debe ser mayor a 0.");
                return;
            }

            // Validar campo 'seguro'
            if (!isset($row['seguro'])) {
                $fail("Fila {$index}: campo seguro requerido.");
                return;
            }

            if (!is_numeric($row['seguro']) || $row['seguro'] < 0) {
                $fail("Fila {$index}: campo seguro debe ser número mayor o igual a 0.");
                return;
            }
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'La estructura del JSON de reservas es inválida.';
    }

    /**
     * Validate date format YYYY-MM-DD
     */
    private function isValidDate($date): bool
    {
        if (!is_string($date)) {
            return false;
        }

        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
