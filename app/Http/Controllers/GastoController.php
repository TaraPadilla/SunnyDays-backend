<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Http\Resources\GastoResource;
use App\Services\FormulaCalculatorService;
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

            $gastos = $query->orderBy('id', 'desc')->get();

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
            'all_params' => $request->all(),
            'query_params' => $request->query(),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
            'inmueble_id' => $request->input('inmueble_id'),
            'filled_fecha_desde' => $request->filled('fecha_desde'),
            'filled_fecha_hasta' => $request->filled('fecha_hasta'),
            'filled_inmueble_id' => $request->filled('inmueble_id')
        ]);

        try {
            // Construir contexto de reservas si viene en el request
            $contexto = [];
            if ($request->filled('json_reservas')) {
                $jsonReservas = $request->input('json_reservas');
                
                Log::info('[GastoController] generarBalance: json_reservas recibido', [
                    'json_reservas' => $jsonReservas
                ]);
                
                // Tomar directamente el total como reservas_subtotal
                $reservasSubtotal = isset($jsonReservas['total']) ? (float) $jsonReservas['total'] : 0.0;
                
                // Inicializar contadores
                $reservasNoches = 0.0;
                $reservasSeguro = 0.0;
                
                // Recorrer el array de reservas para calcular noches y seguro
                if (isset($jsonReservas['reservas']) && is_array($jsonReservas['reservas'])) {
                    foreach ($jsonReservas['reservas'] as $reserva) {
                        $reservasNoches += isset($reserva['noches']) ? (float) $reserva['noches'] : 0.0;
                        $reservasSeguro += isset($reserva['seguro']) ? (float) $reserva['seguro'] : 0.0;
                    }
                }
                
                // Crear el contexto
                $contexto = [
                    'reservas_subtotal' => $reservasSubtotal,
                    'reservas_noches' => $reservasNoches,
                    'reservas_seguro' => $reservasSeguro
                ];
                
                Log::info('[ContextoReservas]', $contexto);
                
                // Asignar el contexto al servicio de cálculo de fórmulas
                FormulaCalculatorService::setContext($contexto);
            }
            
            Log::debug('[GastoController] generarBalance: construyendo consulta con filtros');
            $query = Gasto::with(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            // Aplicar filtros si existen
            if ($request->filled('fecha_desde')) {
                $fechaDesde = $request->input('fecha_desde');
                Log::info('[GastoController] generarBalance: fecha_desde recibida', ['fecha_desde' => $fechaDesde, 'tipo' => gettype($fechaDesde)]);
                $query->whereDate('fecha', '>=', $fechaDesde);
                Log::debug('[GastoController] generarBalance: aplicando filtro fecha_desde', ['fecha_desde' => $fechaDesde]);
            }

            if ($request->filled('fecha_hasta')) {
                $fechaHasta = $request->input('fecha_hasta');
                Log::info('[GastoController] generarBalance: fecha_hasta recibida', ['fecha_hasta' => $fechaHasta, 'tipo' => gettype($fechaHasta)]);
                $query->whereDate('fecha', '<=', $fechaHasta);
                Log::debug('[GastoController] generarBalance: aplicando filtro fecha_hasta', ['fecha_hasta' => $fechaHasta]);
            }

            if ($request->filled('inmueble_id')) {
                $query->where('inmueble_id', $request->inmueble_id);
                Log::debug('[GastoController] generarBalance: aplicando filtro inmueble_id', ['inmueble_id' => $request->inmueble_id]);
            }

            // Log de la consulta SQL antes de ejecutarla
            Log::info('[GastoController] generarBalance: SQL query', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $gastos = $query->orderBy('id', 'desc')->get();

            Log::info('[GastoController] generarBalance: resultados de la consulta', [
                'total_gastos_en_query' => $gastos->count(),
                'gastos_ids' => $gastos->pluck('id')->toArray(),
                'primer_gasto_fecha' => $gastos->first()?->fecha,
                'ultimo_gasto_fecha' => $gastos->last()?->fecha,
                'primer_gasto_id' => $gastos->first()?->id,
                'query_sql' => $query->toSql(),
                'query_bindings' => $query->getBindings()
            ]);

            // Si no hay gastos, verificar qué fechas existen en la BD
            if ($gastos->isEmpty()) {
                Log::info('[GastoController] generarBalance: no se encontraron gastos para los filtros especificados');
                
                // Consulta de depuración para ver qué fechas existen
                $fechasExistentes = \App\Models\Gasto::selectRaw('MIN(fecha) as fecha_min, MAX(fecha) as fecha_max, COUNT(*) as total_gastos')
                    ->whereNull('deleted_at')
                    ->first();
                
                $ultimosGastos = \App\Models\Gasto::select('id', 'fecha', 'monto_total')
                    ->whereNull('deleted_at')
                    ->orderBy('fecha', 'desc')
                    ->limit(5)
                    ->get();
                
                Log::info('[GastoController] generarBalance: depuración de fechas', [
                    'fecha_min' => $fechasExistentes->fecha_min,
                    'fecha_max' => $fechasExistentes->fecha_max,
                    'total_gastos_bd' => $fechasExistentes->total_gastos,
                    'ultimos_gastos' => $ultimosGastos->toArray()
                ]);
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'No se encontraron gastos para los filtros especificados',
                    'data' => [],
                    'balance' => [],
                    'debug' => [
                        'fecha_min_bd' => $fechasExistentes->fecha_min,
                        'fecha_max_bd' => $fechasExistentes->fecha_max,
                        'total_gastos_bd' => $fechasExistentes->total_gastos,
                        'ultimos_gastos' => $ultimosGastos
                    ]
                ], 200);
            }

            Log::debug('[GastoController] generarBalance: calculando balances con ' . $gastos->count() . ' gastos');

            // Construir el objeto Balance ordenado
            $balance = [];

            // Obtener todas las categorías ordenadas para incluir las compuestas sin gastos directos, excluyendo las de tipo Ingreso
            $todasLasCategorias = \App\Models\Categoria::with(['campo', 'subcategorias.campo'])
                ->where('tipo', '!=', 'Ingreso')
                ->orderBy('orden', 'asc')
                ->get();

            // Agrupar gastos por categoría
            $gastosPorCategoria = $gastos->groupBy(function ($gasto) {
                // Validar que la categoría no sea null para evitar error
                if ($gasto->categoria === null) {
                    Log::warning('[GastoController] generarBalance: gasto sin categoría', [
                        'gasto_id' => $gasto->id,
                        'gasto_descripcion' => $gasto->descripcion
                    ]);
                    return 'sin_categoria'; // Agrupar gastos sin categoría
                }
                return $gasto->categoria->id;
            });

            foreach ($todasLasCategorias as $categoria) {
                $balance[$categoria->id] = [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'tipo' => $categoria->tipo,
                    'orden' => $categoria->orden,
                    'subcategorias' => []
                ];

                // Si la categoría tiene gastos directos, procesarlos
                if (isset($gastosPorCategoria[$categoria->id])) {
                    $gastosCategoria = $gastosPorCategoria[$categoria->id];
                    
                    // Agrupar por subcategoría dentro de la categoría
                    $gastosPorSubcategoria = $gastosCategoria->groupBy(function ($gasto) {
                        return $gasto->subcategoria->id;
                    });

                    foreach ($gastosPorSubcategoria as $subcategoriaId => $gastosSubcategoria) {
                        $subcategoria = $gastosSubcategoria->first()->subcategoria;
                        $campo = $subcategoria->campo;

                        $subtotalValue = $subcategoria->subtotal();
                        $valorFormateado = strpos($subcategoria->nombre, '%') !== false ? $subtotalValue . '%' : $subtotalValue;
                        
                        $balance[$categoria->id]['subcategorias'][$subcategoriaId] = [
                            'id' => $subcategoria->id,
                            'nombre' => $subcategoria->nombre,
                            'valor' => $valorFormateado,
                            'orden' => $subcategoria->orden,
                            'tipo_calculo' => $campo ? $campo->tipo_calculo : null
                        ];
                    }
                } else {
                    // Si no tiene gastos directos pero es compuesta, incluir sus subcategorías ordenadas
                    if ($categoria->campo && $categoria->campo->tipo_calculo === 'COMPUESTA') {
                        $subcategoriasOrdenadas = $categoria->subcategorias->sortBy('orden');
                        foreach ($subcategoriasOrdenadas as $subcategoria) {
                            $subtotalValue = $subcategoria->subtotal();
                            $valorFormateado = strpos($subcategoria->nombre, '%') !== false ? $subtotalValue . '%' : $subtotalValue;
                            
                            $balance[$categoria->id]['subcategorias'][$subcategoria->id] = [
                                'id' => $subcategoria->id,
                                'nombre' => $subcategoria->nombre,
                                'valor' => $valorFormateado,
                                'orden' => $subcategoria->orden,
                                'tipo_calculo' => $subcategoria->campo ? $subcategoria->campo->tipo_calculo : null
                            ];
                        }
                    }
                }

                // También incluir subcategorías con fórmula COMPUESTA que no tengan gastos directos
                foreach ($categoria->subcategorias as $subcategoria) {
                    // Si la subcategoría no está en el balance pero tiene fórmula compuesta, incluirla
                    if (!isset($balance[$categoria->id]['subcategorias'][$subcategoria->id]) && 
                        $subcategoria->campo && 
                        $subcategoria->campo->tipo_calculo === 'COMPUESTA') {
                        $subtotalValue = $subcategoria->subtotal();
                        $valorFormateado = strpos($subcategoria->nombre, '%') !== false ? $subtotalValue . '%' : $subtotalValue;
                        
                        $balance[$categoria->id]['subcategorias'][$subcategoria->id] = [
                            'id' => $subcategoria->id,
                            'nombre' => $subcategoria->nombre,
                            'valor' => $valorFormateado,
                            'orden' => $subcategoria->orden,
                            'tipo_calculo' => $subcategoria->campo ? $subcategoria->campo->tipo_calculo : null
                        ];
                    }
                }

                // Calcular subtotal para todas las categorías (incluidas las compuestas)
                if ($categoria->visible_sum) {
                    $balance[$categoria->id]['subtotal'] = $categoria->subtotal();
                }
            }

            // Verificar los datos que se van a retornar
            $dataToReturn = GastoResource::collection($gastos);
            Log::info('[GastoController] generarBalance: datos a retornar', [
                'categorias_en_balance' => count($balance),
                'gastos_en_data_count' => $dataToReturn->count(),
                'gastos_originales_count' => $gastos->count(),
                'data_es_collection' => is_a($dataToReturn, \Illuminate\Http\Resources\Json\AnonymousResourceCollection::class),
                'gastos_array_count' => count($dataToReturn->toArray(request()))
            ]);

            Log::info('[GastoController] generarBalance: éxito', ['categorias' => count($balance)]);

            $response = response()->json([
                'status' => 'success',
                'message' => 'Balance generado correctamente',
                'data' => $balance
            ], 200);
            
            // Limpiar el contexto después de usarlo
            FormulaCalculatorService::setContext([]);
            
            return $response;
        } catch (\Exception $e) {
            Log::error('[GastoController] generarBalance: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Limpiar el contexto incluso en caso de error
            FormulaCalculatorService::setContext([]);

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
                'subcategoria_id' => [
                    'required',
                    'exists:subcategorias,id',
                    function ($attribute, $value, $fail) use ($request) {
                        // Verificar que la subcategoría pertenezca a la categoría seleccionada
                        $subcategoria = \App\Models\Subcategoria::find($value);
                        if ($subcategoria && $subcategoria->categoria_id != $request->categoria_id) {
                            $fail('La subcategoría seleccionada no pertenece a la categoría seleccionada.');
                        }
                    },
                ],
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
