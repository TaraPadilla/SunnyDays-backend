<?php

namespace App\Services;

use App\Models\Campo;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Support\Facades\Log;

class FormulaCalculatorService
{
    /**
     * Cache para evitar cálculos recursivos infinitos
     */
    private static $calculationCache = [];

    /**
     * Contexto externo para valores MANUAL
     */
    private static $context = [];

    /**
     * Asigna el contexto externo para valores MANUAL
     */
    public static function setContext(array $context): void
    {
        self::$context = $context;
    }

    /**
     * Calcula el subtotal de una categoría o subcategoría
     * Este es el método principal que se llama desde el balance
     */
    public static function calculateSubtotal($parentContext): float
    {
        if (!$parentContext) {
            return 0;
        }

        // Generar cache key para evitar recursión infinita
        $cacheKey = self::generateCacheKey($parentContext);
        if (isset(self::$calculationCache[$cacheKey])) {
            return self::$calculationCache[$cacheKey];
        }

        // Marcar como en proceso para detectar ciclos
        self::$calculationCache[$cacheKey] = 0;

        try {
            $result = self::processByType($parentContext);
            self::$calculationCache[$cacheKey] = $result;
            return $result;
        } catch (\Exception $e) {
            // Limpiar cache en caso de error
            unset(self::$calculationCache[$cacheKey]);
            throw $e;
        }
    }

    /**
     * Procesa según el tipo de cálculo del campo asociado
     */
    private static function processByType($parentContext): float
    {
        $campo = self::getCampoFromContext($parentContext);
        
        if (!$campo) {
            return 0;
        }

        Log::debug('[FormulaCalculatorService] processByType', [
            'context_type' => get_class($parentContext),
            'context_id' => $parentContext->id,
            'campo_tipo_calculo' => $campo->tipo_calculo,
            'campo_formula' => $campo->formula
        ]);

        switch ($campo->tipo_calculo) {
            case 'SUM':
                return self::calculateSum($parentContext);
            case 'COMPUESTA':
                return self::processFormula($campo, $parentContext);
            case 'MANUAL':
                return self::getContextValue($campo->clave);
            default:
                return 0;
        }
    }

    /**
     * Obtiene el campo asociado al contexto (categoría o subcategoría)
     */
    private static function getCampoFromContext($parentContext): ?Campo
    {
        if ($parentContext instanceof Categoria) {
            return $parentContext->campo;
        } elseif ($parentContext instanceof Subcategoria) {
            return $parentContext->campo;
        }
        return null;
    }

    /**
     * Calcula la suma directa para tipo SUM
     */
    private static function calculateSum($parentContext): float
    {
        if ($parentContext instanceof Categoria) {
            // Sumar subtotales de todas las subcategorías
            $total = 0;
            foreach ($parentContext->subcategorias as $subcategoria) {
                $total += self::calculateSubtotal($subcategoria);
            }
            
            Log::debug('[FormulaCalculatorService] calculateSum', [
                'type' => 'Categoria',
                'categoria_id' => $parentContext->id,
                'subcategorias_count' => $parentContext->subcategorias->count(),
                'total' => $total
            ]);
            
            return $total;
        } elseif ($parentContext instanceof Subcategoria) {
            // Sumar montos de todos los gastos
            $total = $parentContext->gastos()->sum('monto_total');
            
            Log::debug('[FormulaCalculatorService] calculateSum', [
                'type' => 'Subcategoria',
                'subcategoria_id' => $parentContext->id,
                'gastos_count' => $parentContext->gastos()->count(),
                'total' => $total
            ]);
            
            return $total;
        }
        
        return 0;
    }

    /**
     * Procesa una fórmula compuesta
     */
    private static function processFormula(Campo $campo, $parentContext): float
    {
        if (!$campo->formula) {
            return 0;
        }

        Log::debug('[FormulaCalculatorService] processFormula', [
            'campo_id' => $campo->id,
            'formula' => $campo->formula,
            'context_id' => $parentContext->id
        ]);

        return self::evaluateFormula($campo->formula, $parentContext);
    }

    /**
     * Evalúa una fórmula matemática
     */
    private static function evaluateFormula(string $formula, $parentContext): float
    {
        // Primero procesar paréntesis
        $formula = self::processParentheses($formula, $parentContext);
        
        // Luego procesar la fórmula sin paréntesis
        return self::evaluateSimpleFormula($formula, $parentContext);
    }

    /**
     * Procesa paréntesis recursivamente
     */
    private static function processParentheses(string $formula, $parentContext): string
    {
        while (preg_match('/\(([^()]+)\)/', $formula, $matches)) {
            $innerFormula = trim($matches[1]);
            $result = self::evaluateSimpleFormula($innerFormula, $parentContext);
            $formula = str_replace($matches[0], $result, $formula);
        }
        
        return $formula;
    }

    /**
     * Evalúa una fórmula sin paréntesis
     */
    private static function evaluateSimpleFormula(string $formula, $parentContext): float
    {
        $formula = trim($formula);
        
        // Tokenizar la fórmula
        $tokens = self::tokenizeFormula($formula);
        
        Log::debug('[FormulaCalculatorService] evaluateSimpleFormula', [
            'formula' => $formula,
            'tokens' => $tokens
        ]);
        
        // Procesar multiplicación y división primero
        $tokens = self::processMultiplicationDivision($tokens, $parentContext);
        
        // Luego procesar suma y resta
        $result = self::processAdditionSubtraction($tokens, $parentContext);
        
        Log::debug('[FormulaCalculatorService] evaluateSimpleFormula result', [
            'formula' => $formula,
            'result' => $result
        ]);
        
        return $result;
    }

    /**
     * Tokeniza una fórmula en números, operadores y palabras clave
     */
    private static function tokenizeFormula(string $formula): array
    {
        // Patrón para capturar: SUM, identificadores alfanuméricos (incluyendo hex), números, operadores
        // Los identificadores pueden contener: letras mayúsculas, números, guiones bajos, y caracteres hex
        $pattern = '/(SUM|[A-Z][A-Z0-9a-f_]*|\d+\.?\d*|[+\-*\/])/';
        preg_match_all($pattern, $formula, $matches);
        
        return $matches[0];
    }

    /**
     * Procesa multiplicación y división
     */
    private static function processMultiplicationDivision(array $tokens, $parentContext): array
    {
        // Primero resolver todos los tokens no numéricos (SUM y referencias)
        $resolvedTokens = [];
        foreach ($tokens as $token) {
            if (!is_numeric($token) && !in_array($token, ['*', '/', '+', '-'])) {
                $resolvedTokens[] = self::getTokenValue($token, $parentContext);
            } else {
                $resolvedTokens[] = $token;
            }
        }
        
        $result = [];
        $i = 0;
        
        while ($i < count($resolvedTokens)) {
            $token = $resolvedTokens[$i];
            
            if (in_array($token, ['*', '/']) && isset($result[count($result) - 1]) && isset($resolvedTokens[$i + 1])) {
                $left = $result[count($result) - 1];
                $right = $resolvedTokens[$i + 1];
                
                // Asegurar que ambos operandos sean numéricos
                $leftValue = is_numeric($left) ? (float)$left : $left;
                $rightValue = is_numeric($right) ? (float)$right : $right;
                
                if ($token === '*') {
                    $result[count($result) - 1] = $leftValue * $rightValue;
                } else {
                    // Evitar división por cero
                    if ($rightValue == 0) {
                        \Log::debug('Division by zero detected in FormulaCalculatorService', [
                            'leftValue' => $leftValue,
                            'rightValue' => $rightValue,
                            'operation' => 'division'
                        ]);
                        $result[count($result) - 1] = 0;
                    } else {
                        $result[count($result) - 1] = $leftValue / $rightValue;
                    }
                }
                
                $i += 2;
            } else {
                $result[] = $token;
                $i++;
            }
        }
        
        return $result;
    }

    /**
     * Procesa suma y resta
     */
    private static function processAdditionSubtraction(array $tokens, $parentContext): float
    {
        $result = 0;
        $operator = '+';
        
        foreach ($tokens as $token) {
            if (in_array($token, ['+', '-'])) {
                $operator = $token;
            } else {
                $value = self::getTokenValue($token, $parentContext);
                
                // Asegurar que el valor sea numérico
                $numericValue = is_numeric($value) ? (float)$value : $value;
                
                if ($operator === '+') {
                    $result += $numericValue;
                } else {
                    $result -= $numericValue;
                }
            }
        }
        
        return $result;
    }

    /**
     * Obtiene el valor numérico de un token
     */
    private static function getTokenValue(string $token, $parentContext): float
    {
        // Si es un número
        if (is_numeric($token)) {
            return (float) $token;
        }
        
        // Si es SUM, retorna el subtotal del contexto actual
        if ($token === 'SUM') {
            return self::calculateSum($parentContext);
        }
        
        // Si es una clave de campo, buscar y procesar recursivamente
        return self::processCampoReference($token);
    }

    /**
     * Procesa una referencia a un campo
     */
    private static function processCampoReference(string $clave): float
    {
        Log::debug('[FormulaCalculatorService] processCampoReference', [
            'clave' => $clave
        ]);
        
        // Búsqueda case-insensitive de la clave
        $campo = Campo::whereRaw('LOWER(clave) = ?', [strtolower($clave)])->first();
        
        if (!$campo) {
            Log::warning('[FormulaCalculatorService] Campo no encontrado', ['clave' => $clave]);
            return 0;
        }
        
        // Si el campo es de tipo MANUAL, obtener su valor del contexto externo
        if ($campo->tipo_calculo === 'MANUAL') {
            $claveNormalizada = strtolower($campo->clave);
            $valor = isset(self::$context[$claveNormalizada]) 
                ? (float) self::$context[$claveNormalizada] 
                : 0;
            
            Log::debug('[FormulaCalculatorService] Campo MANUAL encontrado', [
                'clave' => $clave,
                'campo_id' => $campo->id,
                'valor_contexto' => $valor
            ]);
            
            return $valor;
        }
        
        // Buscar la categoría o subcategoría asociada a este campo
        $categoria = Categoria::where('campo_id', $campo->id)->first();
        $subcategoria = Subcategoria::where('campo_id', $campo->id)->first();
        
        if ($categoria) {
            Log::debug('[FormulaCalculatorService] Procesando campo con categoría', [
                'clave' => $clave,
                'categoria_id' => $categoria->id
            ]);
            return self::calculateSubtotal($categoria);
        } elseif ($subcategoria) {
            Log::debug('[FormulaCalculatorService] Procesando campo con subcategoría', [
                'clave' => $clave,
                'subcategoria_id' => $subcategoria->id
            ]);
            return self::calculateSubtotal($subcategoria);
        }
        
        Log::warning('[FormulaCalculatorService] No se encontró categoría/subcategoría asociada', [
            'clave' => $clave,
            'campo_id' => $campo->id
        ]);
        
        return 0;
    }

    /**
     * Genera una clave de cache única
     */
    private static function generateCacheKey($parentContext): string
    {
        return get_class($parentContext) . ':' . $parentContext->id;
    }

    /**
     * Limpia el cache de cálculos
     */
    public static function clearCache(): void
    {
        self::$calculationCache = [];
    }

    /**
     * Obtiene un valor del contexto externo
     */
    private static function getContextValue(string $clave): float
    {
        return isset(self::$context[$clave]) ? (float) self::$context[$clave] : 0;
    }
}