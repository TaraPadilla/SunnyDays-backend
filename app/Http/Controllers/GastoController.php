<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Http\Resources\GastoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GastoController extends Controller
{
    /**
     * Display a listing of gastos with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('[GastoController] index: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path()
        ]);

        try {
            Log::debug('[GastoController] index: obteniendo último gasto');
            $gasto = Gasto::with(['inmueble', 'categoria.campo', 'subcategoria.campo'])
                ->orderBy('fecha', 'desc')
                ->first();

            if (!$gasto) {
                Log::info('[GastoController] index: no hay gastos');

                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay gastos registrados',
                    'data' => null
                ], 200);
            }

            Log::info('[GastoController] index: éxito', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Último gasto obtenido correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GastoController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el último gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a filtered listing of gastos.
     */
    public function gastosFiltrados(Request $request): JsonResponse
    {
        Log::info('[GastoController] gastosFiltrados: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'filters' => $request->all(['fecha_desde', 'fecha_hasta', 'inmueble_id'])
        ]);

        try {
            Log::debug('[GastoController] gastosFiltrados: construyendo consulta con filtros');
            $query = Gasto::with(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            // Aplicar filtros si existen
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha', '>=', $request->fecha_desde);
                Log::debug('[GastoController] gastosFiltrados: aplicando filtro fecha_desde', ['fecha_desde' => $request->fecha_desde]);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha', '<=', $request->fecha_hasta);
                Log::debug('[GastoController] gastosFiltrados: aplicando filtro fecha_hasta', ['fecha_hasta' => $request->fecha_hasta]);
            }

            if ($request->filled('inmueble_id')) {
                $query->where('inmueble_id', $request->inmueble_id);
                Log::debug('[GastoController] gastosFiltrados: aplicando filtro inmueble_id', ['inmueble_id' => $request->inmueble_id]);
            }

            $gastos = $query->orderBy('fecha', 'desc')->get();

            Log::info('[GastoController] gastosFiltrados: éxito', ['total' => $gastos->count()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gastos filtrados obtenidos correctamente',
                'data' => GastoResource::collection($gastos)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GastoController] gastosFiltrados: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener gastos filtrados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate balance data with filters.
     */
    public function generarBalance(Request $request): JsonResponse
    {
        Log::info('[GastoController] generarBalance: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'filters' => $request->all(['fecha_desde', 'fecha_hasta', 'inmueble_id'])
        ]);

        try {
            Log::debug('[GastoController] generarBalance: construyendo consulta con filtros');
            $query = Gasto::with(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            // Aplicar filtros si existen
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha', '>=', $request->fecha_desde);
                Log::debug('[GastoController] generarBalance: aplicando filtro fecha_desde', ['fecha_desde' => $request->fecha_desde]);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha', '<=', $request->fecha_hasta);
                Log::debug('[GastoController] generarBalance: aplicando filtro fecha_hasta', ['fecha_hasta' => $request->fecha_hasta]);
            }

            if ($request->filled('inmueble_id')) {
                $query->where('inmueble_id', $request->inmueble_id);
                Log::debug('[GastoController] generarBalance: aplicando filtro inmueble_id', ['inmueble_id' => $request->inmueble_id]);
            }

            $gastos = $query->orderBy('fecha', 'desc')->get();

            Log::debug('[GastoController] generarBalance: calculando balances');

            // Construir el objeto Balance
            $balance = [];

            // Agrupar gastos por categoría
            $gastosPorCategoria = $gastos->groupBy(function ($gasto) {
                return $gasto->categoria->id;
            });

            foreach ($gastosPorCategoria as $categoriaId => $gastosCategoria) {
                $categoria = $gastosCategoria->first()->categoria;

                $balance[$categoriaId] = [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'tipo' => $categoria->tipo,
                    'subcategorias' => []
                ];

                // Agrupar por subcategoría dentro de la categoría
                $gastosPorSubcategoria = $gastosCategoria->groupBy(function ($gasto) {
                    return $gasto->subcategoria->id;
                });

                foreach ($gastosPorSubcategoria as $subcategoriaId => $gastosSubcategoria) {
                    $subcategoria = $gastosSubcategoria->first()->subcategoria;
                    $campo = $subcategoria->campo;

                    $balance[$categoriaId]['subcategorias'][$subcategoriaId] = [
                        'id' => $subcategoria->id,
                        'nombre' => $subcategoria->nombre,
                        'valor' => $subcategoria->subtotal(),
                        'tipo_calculo' => $campo ? $campo->tipo_calculo : null
                    ];
                }

                if ($categoria->visible_sum) {
                    $balance[$categoriaId]['subtotal'] = $categoria->subtotal();
                }
            }

            Log::info('[GastoController] generarBalance: éxito', ['categorias' => count($balance)]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance generado correctamente',
                'data' => GastoResource::collection($gastos),
                'balance' => $balance
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GastoController] generarBalance: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created gasto in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[GastoController] store: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'keys' => array_keys($request->all()),
        ]);

        try {
            // Validate the request - works for both n8n processed data and regular form submission
            Log::debug('[GastoController] store: validando entrada');
            $validated = $request->validate([
                'fecha' => 'required|date',
                'monto_sin_iva' => 'nullable|decimal:0,2|min:0',
                'iva' => 'nullable|decimal:0,2|min:0',
                'monto_total' => 'required|decimal:0,2|min:0',
                'tipo_soporte' => 'nullable|in:Factura,Recibo,Ticket,Otro',
                'descripcion' => 'nullable|string|max:1000',
                'inmueble_id' => 'required|exists:inmuebles,id',
                'categoria_id' => 'required|exists:categorias,id',
                'subcategoria_id' => 'required|exists:subcategorias,id',
                'tipo_pago' => 'nullable|in:Efectivo,Transferencia,Tarjeta,Otro',
                'proveedor' => 'nullable|string|max:255',
                'numero_comprobante' => 'nullable|string|max:255',
                'observaciones' => 'nullable|string|max:1000'
            ]);

            Log::debug('[GastoController] store: validación OK, creando registro');
            $gasto = Gasto::create($validated);
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            Log::info('[GastoController] store: gasto creado', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto creado correctamente',
                'data' => new GastoResource($gasto)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[GastoController] store: validación fallida', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[GastoController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified gasto.
     */
    public function show(Gasto $gasto): JsonResponse
    {
        Log::info('[GastoController] show: petición recibida', [
            'gasto_id' => $gasto->id,
            'trashed' => $gasto->trashed(),
        ]);

        try {
            if ($gasto->trashed()) {
                Log::notice('[GastoController] show: gasto eliminado (soft), 404', ['gasto_id' => $gasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'El gasto no existe o ha sido eliminado'
                ], 404);
            }

            Log::debug('[GastoController] show: cargando relaciones');
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            Log::info('[GastoController] show: éxito', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto obtenido correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GastoController] show: excepción', [
                'gasto_id' => $gasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified gasto in storage.
     */
    public function update(Request $request, Gasto $gasto): JsonResponse
    {
        Log::info('[GastoController] update: petición recibida', [
            'gasto_id' => $gasto->id,
            'trashed' => $gasto->trashed(),
            'keys' => array_keys($request->all()),
        ]);

        try {
            if ($gasto->trashed()) {
                Log::notice('[GastoController] update: intento sobre gasto eliminado, 404', ['gasto_id' => $gasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar un gasto eliminado'
                ], 404);
            }

            Log::debug('[GastoController] update: validando entrada');
            $validated = $request->validate([
                'fecha' => 'required|date',
                'monto_sin_iva' => 'nullable|decimal:0,2|min:0',
                'iva' => 'nullable|decimal:0,2|min:0',
                'monto_total' => 'required|decimal:0,2|min:0',
                'tipo_soporte' => 'nullable|in:Factura,Recibo,Ticket,Otro',
                'descripcion' => 'nullable|string|max:1000',
                'inmueble_id' => 'required|exists:inmuebles,id',
                'categoria_id' => 'required|exists:categorias,id',
                'subcategoria_id' => 'required|exists:subcategorias,id',
                'tipo_pago' => 'nullable|in:Efectivo,Transferencia,Tarjeta,Otro',
                'proveedor' => 'nullable|string|max:255',
                'numero_comprobante' => 'nullable|string|max:255',
                'observaciones' => 'nullable|string|max:1000'
            ]);

            Log::debug('[GastoController] update: validación OK, persistiendo');
            $gasto->update($validated);
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            Log::info('[GastoController] update: éxito', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto actualizado correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[GastoController] update: validación fallida', [
                'gasto_id' => $gasto->id,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[GastoController] update: excepción', [
                'gasto_id' => $gasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified gasto from storage (soft delete).
     */
    public function destroy(Gasto $gasto): JsonResponse
    {
        Log::info('[GastoController] destroy: petición recibida', [
            'gasto_id' => $gasto->id,
            'trashed' => $gasto->trashed(),
        ]);

        try {
            if ($gasto->trashed()) {
                Log::notice('[GastoController] destroy: ya estaba eliminado, 404', ['gasto_id' => $gasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'El gasto ya fue eliminado'
                ], 404);
            }

            Log::debug('[GastoController] destroy: aplicando soft delete');
            $gasto->delete();

            Log::info('[GastoController] destroy: éxito', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[GastoController] destroy: excepción', [
                'gasto_id' => $gasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted gasto.
     */
    public function restore($id): JsonResponse
    {
        Log::info('[GastoController] restore: petición recibida', [
            'id' => $id,
            'method' => request()->method(),
        ]);

        try {
            Log::debug('[GastoController] restore: buscando en papelera');
            $gasto = Gasto::onlyTrashed()->findOrFail($id);
            $gasto->restore();
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            Log::info('[GastoController] restore: éxito', ['gasto_id' => $gasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto restaurado correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::notice('[GastoController] restore: no encontrado en papelera', ['id' => $id]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar gasto',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('[GastoController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
