<?php

namespace App\Services;

use App\Models\Campo;
use App\Models\Categoria;
use App\Models\Subcategoria;

class FormulaCalculatorService
{
    /**
     * Cache para evitar cálculos recursivos infinitos
     */
    private static $calculationCache = [];

    /**
     * Calcula el subtotal basado en el tipo_calculo del campo
     * Método principal que delega a los métodos específicos
     */
    public static function calculateSubtotal($parentContext): float
    {
        if (!$parentContext) {
            return 0;
        }

        // Obtener el campo del contexto
        $campo = null;
        if ($parentContext instanceof Categoria) {
            $campo = $parentContext->campo;
        } elseif ($parentContext instanceof Subcategoria) {
            $campo = $parentContext->campo;
        }

        if (!$campo) {
            return 0;
        }

        // Delegar según el tipo de cálculo
        switch ($campo->tipo_calculo) {
            case 'SUM':
                return self::calculateSum($parentContext);
            case 'COMPUESTA':
                return self::calcularFormula($campo, $parentContext);
            default:
                return 0;
        }
    }

    /**
     * Calcula la suma recursiva para tipo SUM
     */
    private static function calculateSum($parentContext): float
    {
        if ($parentContext instanceof Categoria) {
            $subtotal = 0;
            foreach ($parentContext->subcategorias as $subcategoria) {
                $subtotal += self::calculateSubtotal($subcategoria);
            }
            return $subtotal;
        } elseif ($parentContext instanceof Subcategoria) {
            return $parentContext->gastos()->sum('monto_total');
        }

        return 0;
    }

    /**
     * Calcula el valor de la fórmula de forma recursiva
     * Maneja operadores matemáticos con precedencia y paréntesis
     */
    public static function calcularFormula(Campo $campo, $parentContext = null): float
    {
        // Si el tipo no es COMPUESTA, retornar 0
        if ($campo->tipo_calculo !== 'COMPUESTA' || !$campo->formula) {
            return 0;
        }

        // Evitar cálculos recursivos infinitos
        $cacheKey = self::generateCacheKey($campo->id, $parentContext);
        if (isset(self::$calculationCache[$cacheKey])) {
            return self::$calculationCache[$cacheKey];
        }

        // Marcar como en proceso para detectar ciclos
        self::$calculationCache[$cacheKey] = 0;

        try {
            $result = self::parseAndEvaluateFormula($campo->formula, $parentContext);
            self::$calculationCache[$cacheKey] = $result;
            return $result;
        } catch (\Exception $e) {
            // Limpiar cache en caso de error
            unset(self::$calculationCache[$cacheKey]);
            throw $e;
        }
    }

    /**
     * Parsea y evalúa la fórmula con manejo de operadores y paréntesis
     */
    private static function parseAndEvaluateFormula(string $formula, $parentContext = null): float
    {
        \Log::debug('[FormulaCalculatorService] parseAndEvaluateFormula: inicio', ['formula' => $formula]);
        
        // Limpiar la fórmula
        $formula = trim($formula);
        
        // Manejar paréntesis - evaluar recursivamente el contenido
        while (preg_match('/\(([^()]+)\)/', $formula, $matches)) {
            \Log::debug('[FormulaCalculatorService] parseAndEvaluateFormula: procesando paréntesis', [
                'parenthesis_content' => $matches[1],
                'full_match' => $matches[0]
            ]);
            
            $innerResult = self::parseAndEvaluateFormula($matches[1], $parentContext);
            $formula = str_replace($matches[0], $innerResult, $formula);
            
            \Log::debug('[FormulaCalculatorService] parseAndEvaluateFormula: paréntesis resuelto', [
                'replaced' => $matches[0],
                'result' => $innerResult,
                'formula_after' => $formula
            ]);
        }

        \Log::debug('[FormulaCalculatorService] parseAndEvaluateFormula: fórmula sin paréntesis', ['formula' => $formula]);

        // Tokenizar la fórmula
        $tokens = self::tokenizeFormula($formula);
        
        // Evaluar con precedencia de operadores
        $result = self::evaluateTokens($tokens, $parentContext);
        
        \Log::debug('[FormulaCalculatorService] parseAndEvaluateFormula: resultado final', [
            'formula' => $formula,
            'result' => $result
        ]);
        
        return $result;
    }

    /**
     * Tokeniza la fórmula en números, operadores y funciones
     * Caracteres permitidos:
     * - Números: 123, 45.67, 0.5
     * - Claves: SUM, CAT_A, SUB_GEN, CAT_69e42172e983d
     * - Operadores: +, -, *, /, (, )
     */
    private static function tokenizeFormula(string $formula): array
    {
        // Patrón que reconoce:
        // 1. Números decimales: \d+\.?\d*
        // 2. SUM como palabra clave: SUM\b
        // 3. Claves alfanuméricas con guiones bajos: [A-Z_][A-Z0-9_a-fA-F-]*
        // 4. Operadores: [+*/()-]
        $pattern = '/(\d+\.?\d*|SUM\b|[A-Z_][A-Z0-9_a-fA-F-]*|[+\-*\/\(\)])/';
        preg_match_all($pattern, $formula, $matches);
        
        $tokens = $matches[1];
        
        \Log::debug('[FormulaCalculatorService] tokenizeFormula: fórmula procesada', [
            'formula' => $formula,
            'tokens' => $tokens
        ]);
        
        return $tokens;
    }

    /**
     * Evalúa los tokens con precedencia de operadores
     */
    private static function evaluateTokens(array $tokens, $parentContext = null): float
    {
        \Log::debug('[FormulaCalculatorService] evaluateTokens: tokens iniciales', ['tokens' => $tokens]);
        
        // Primera pasada: manejar SUM y referencias a campos
        $evaluatedTokens = [];
        foreach ($tokens as $token) {
            if ($token === 'SUM') {
                $sumValue = self::handleSumOperation($parentContext);
                $evaluatedTokens[] = $sumValue;
                \Log::debug('[FormulaCalculatorService] evaluateTokens: SUM procesado', ['token' => $token, 'value' => $sumValue]);
            } elseif (preg_match('/^[A-Z_]/', $token) && !is_numeric($token) && !in_array($token, ['+', '-', '*', '/', '(', ')'])) {
                // Es una clave de campo (empieza con letra y no es operador)
                $campoValue = self::handleCampoReference($token, $parentContext);
                $evaluatedTokens[] = $campoValue;
                \Log::debug('[FormulaCalculatorService] evaluateTokens: campo referenciado', ['token' => $token, 'value' => $campoValue]);
            } else {
                // Es número u operador
                $evaluatedTokens[] = $token;
            }
        }
        
        \Log::debug('[FormulaCalculatorService] evaluateTokens: tokens evaluados', ['evaluatedTokens' => $evaluatedTokens]);

        // Segunda pasada: multiplicación y división
        $tokens = self::evaluateMultiplicationDivision($evaluatedTokens);

        // Tercera pasada: suma y resta
        return self::evaluateAdditionSubtraction($tokens);
    }

    /**
     * Maneja la operación SUM() - calcula directamente el subtotal sin recursión
     */
    private static function handleSumOperation($parentContext): float
    {
        \Log::debug('[FormulaCalculatorService] handleSumOperation: iniciando', [
            'parentContext_type' => $parentContext ? get_class($parentContext) : 'null',
            'parentContext_id' => $parentContext ? $parentContext->id : 'null'
        ]);

        if (!$parentContext) {
            return 0;
        }

        if ($parentContext instanceof Categoria) {
            // Calcular directamente la suma de subcategorías sin llamar a subtotal()
            $subtotal = 0;
            foreach ($parentContext->subcategorias as $subcategoria) {
                $subtotal += self::calculateSubtotal($subcategoria);
            }
            \Log::debug('[FormulaCalculatorService] handleSumOperation: suma de categorías', [
                'categoria_id' => $parentContext->id,
                'subtotal' => $subtotal
            ]);
            return $subtotal;
        } elseif ($parentContext instanceof Subcategoria) {
            // Calcular directamente la suma de gastos sin llamar a subtotal()
            $subtotal = $parentContext->gastos()->sum('monto_total');
            \Log::debug('[FormulaCalculatorService] handleSumOperation: suma de subcategoría', [
                'subcategoria_id' => $parentContext->id,
                'subtotal' => $subtotal
            ]);
            return $subtotal;
        }

        return 0;
    }

    /**
     * Maneja referencias a otros campos
     */
    private static function handleCampoReference(string $clave, $parentContext): float
    {
        $campoReferenciado = Campo::where('clave', $clave)->first();
        
        if (!$campoReferenciado) {
            throw new \Exception("Campo con clave '{$clave}' no encontrado");
        }

        return self::calcularFormula($campoReferenciado, $parentContext);
    }

    /**
     * Evalúa multiplicación y división
     */
    private static function evaluateMultiplicationDivision(array $tokens): array
    {
        \Log::debug('[FormulaCalculatorService] evaluateMultiplicationDivision: tokens iniciales', ['tokens' => $tokens]);
        
        $result = [];
        $i = 0;
        
        while ($i < count($tokens)) {
            $token = $tokens[$i];
            
            if (in_array($token, ['*', '/']) && isset($result[count($result) - 1]) && isset($tokens[$i + 1])) {
                $left = $result[count($result) - 1];
                $right = $tokens[$i + 1];
                
                // Convertir a números si son strings numéricos
                $leftValue = is_numeric($left) ? (float)$left : $left;
                $rightValue = is_numeric($right) ? (float)$right : $right;
                
                \Log::debug('[FormulaCalculatorService] evaluateMultiplicationDivision: operación', [
                    'operator' => $token,
                    'left' => $left,
                    'leftValue' => $leftValue,
                    'right' => $right,
                    'rightValue' => $rightValue
                ]);
                
                if ($token === '*') {
                    if (!is_numeric($leftValue) || !is_numeric($rightValue)) {
                        throw new \Exception("Operandos no numéricos en multiplicación: {$leftValue} * {$rightValue}");
                    }
                    $result[count($result) - 1] = $leftValue * $rightValue;
                } else {
                    if (!is_numeric($rightValue)) {
                        throw new \Exception("Operador derecho no numérico en división: {$rightValue}");
                    }
                    if ($rightValue == 0) {
                        throw new \Exception("División por cero");
                    }
                    if (!is_numeric($leftValue)) {
                        throw new \Exception("Operador izquierdo no numérico en división: {$leftValue}");
                    }
                    $result[count($result) - 1] = $leftValue / $rightValue;
                }
                
                $i += 2;
            } else {
                $result[] = $token;
                $i++;
            }
        }
        
        \Log::debug('[FormulaCalculatorService] evaluateMultiplicationDivision: tokens finales', ['result' => $result]);
        
        return $result;
    }

    /**
     * Evalúa suma y resta
     */
    private static function evaluateAdditionSubtraction(array $tokens): float
    {
        $result = 0;
        $currentOperator = '+';
        
        foreach ($tokens as $token) {
            if (in_array($token, ['+', '-'])) {
                $currentOperator = $token;
            } else {
                $value = is_numeric($token) ? (float)$token : 0;
                
                if ($currentOperator === '+') {
                    $result += $value;
                } else {
                    $result -= $value;
                }
            }
        }
        
        return $result;
    }

    /**
     * Genera una clave única para el cache
     */
    private static function generateCacheKey($campoId, $parentContext): string
    {
        $parentKey = $parentContext ? get_class($parentContext) . ':' . $parentContext->id : 'null';
        return "campo_{$campoId}_parent_{$parentKey}";
    }

    /**
     * Limpia el cache de cálculos
     */
    public static function clearCalculationCache(): void
    {
        self::$calculationCache = [];
    }
}
