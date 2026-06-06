<?php

namespace App\Http\Controllers;

use App\Models\Subcategoria;
use App\Models\Campo;
use App\Models\Categoria;
use App\Models\Gasto;
use App\Http\Resources\SubcategoriaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SubcategoriaController extends Controller
{
    /**
     * Display a listing of subcategorias.
     */
    public function index(): JsonResponse
    {
        Log::info('[SubcategoriaController] index: petición recibida');
        
        try {
            $user = auth()->user();
            $isAdmin = $user && $user->perfil === 'admin';
            
            $query = Subcategoria::where('estado', true)
                ->where('visible_combo', true)
                ->with(['categoria', 'campo'])
                ->orderBy('orden');
            
            // If not admin, only return subcategories under expense categories
            if (!$isAdmin) {
                $query->whereHas('categoria', function($categoryQuery) {
                    $categoryQuery->where('tipo', 'Egreso');
                });
            }
            
            $subcategorias = $query->get();
            
            Log::info('[SubcategoriaController] index: éxito', [
                'total' => $subcategorias->count(),
                'is_admin' => $isAdmin,
                'filter' => !$isAdmin ? 'Egreso categories only' : 'none'
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subcategorías obtenidas correctamente',
                'data' => SubcategoriaResource::collection($subcategorias)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener subcategorías',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subcategories by category ID.
     */
    public function getByCategoria(Request $request, int $categoriaId): JsonResponse
    {
        Log::info('[SubcategoriaController] getByCategoria: petición recibida', [
            'categoria_id' => $categoriaId
        ]);
        
        try {
            $user = auth()->user();
            $isAdmin = $user && $user->perfil === 'admin';
            
            $query = Subcategoria::where('categoria_id', $categoriaId)
                ->where('estado', true)
                ->where('visible_combo', true)
                ->with(['categoria', 'campo'])
                ->orderBy('orden')
                ->orderBy('nombre');
            
            // If not admin, only return subcategories under expense categories
            if (!$isAdmin) {
                $query->whereHas('categoria', function($categoryQuery) {
                    $categoryQuery->where('tipo', 'Egreso');
                });
            }
            
            $subcategorias = $query->get();
            
            Log::info('[SubcategoriaController] getByCategoria: éxito', [
                'categoria_id' => $categoriaId,
                'total' => $subcategorias->count(),
                'is_admin' => $isAdmin
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subcategorías obtenidas correctamente',
                'data' => SubcategoriaResource::collection($subcategorias)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] getByCategoria: excepción', [
                'categoria_id' => $categoriaId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener subcategorías',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created subcategoria in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[SubcategoriaController] store: petición recibida', [
            'data' => $request->all()
        ]);
        
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'categoria_id' => 'required|exists:categorias,id',
                'campo_id' => 'nullable|exists:campos,id',
                'visible_combo' => 'nullable|boolean',
                'orden' => 'nullable|integer',
                'estado' => 'nullable|boolean'
            ]);

            // Auto-assign order if it's 0 or null
            if (!isset($validated['orden']) || $validated['orden'] === 0) {
                $activeCount = Subcategoria::where('estado', true)->count();
                $validated['orden'] = $activeCount + 1;
            }

            // Create default field if not provided
            if (!isset($validated['campo_id']) || $validated['campo_id'] === null || $validated['campo_id'] === '') {
                $defaultCampo = Campo::create([
                    'clave' => 'SUB_' . uniqid(),
                    'nombre' => $validated['nombre'],
                    'tipo_calculo' => 'SUM',
                    'estado' => true
                ]);
                $validated['campo_id'] = $defaultCampo->id;
            }

            $subcategoria = Subcategoria::create($validated);
            $subcategoria->load(['categoria', 'campo']);
            
            Log::info('[SubcategoriaController] store: subcategoría creada', [
                'subcategoria_id' => $subcategoria->id,
                'nombre' => $subcategoria->nombre,
                'categoria_id' => $subcategoria->categoria_id,
                'campo_id' => $subcategoria->campo_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría creada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SubcategoriaController] store: validación fallida', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified subcategoria.
     */
    public function show(Subcategoria $subcategoria): JsonResponse
    {
        Log::info('[SubcategoriaController] show: petición recibida', [
            'subcategoria_id' => $subcategoria->id,
            'trashed' => $subcategoria->trashed()
        ]);
        
        try {
            if ($subcategoria->trashed()) {
                Log::notice('[SubcategoriaController] show: subcategoría eliminada', ['subcategoria_id' => $subcategoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'La subcategoría no existe o ha sido eliminada'
                ], 404);
            }

            $subcategoria->load(['categoria', 'campo']);
            
            Log::info('[SubcategoriaController] show: éxito', ['subcategoria_id' => $subcategoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría obtenida correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] show: excepción', [
                'subcategoria_id' => $subcategoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified subcategoria in storage.
     */
    public function update(Request $request, Subcategoria $subcategoria): JsonResponse
    {
        Log::info('[SubcategoriaController] update: petición recibida', [
            'subcategoria_id' => $subcategoria->id,
            'trashed' => $subcategoria->trashed(),
            'data' => $request->all()
        ]);
        
        try {
            if ($subcategoria->trashed()) {
                Log::notice('[SubcategoriaController] update: intento sobre subcategoría eliminada', ['subcategoria_id' => $subcategoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar una subcategoría eliminada'
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'categoria_id' => 'nullable|exists:categorias,id',
                'campo_id' => 'nullable|exists:campos,id',
                'visible_combo' => 'nullable|boolean',
                'orden' => 'nullable|integer',
                'estado' => 'nullable|boolean'
            ]);

            // Validar que no se esté cambiando la categoría si hay gastos asociados
            if (isset($validated['categoria_id']) && $validated['categoria_id'] != $subcategoria->categoria_id) {
                $gastosCount = Gasto::where('subcategoria_id', $subcategoria->id)->count();
                if ($gastosCount > 0) {
                    Log::warning('[SubcategoriaController] update: intento de cambiar categoría con gastos asociados', [
                        'subcategoria_id' => $subcategoria->id,
                        'categoria_actual' => $subcategoria->categoria_id,
                        'categoria_nueva' => $validated['categoria_id'],
                        'gastos_count' => $gastosCount
                    ]);
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se puede cambiar la categoría de esta subcategoría porque tiene gastos asociados. Use el endpoint específico para mover la categoría.'
                    ], 422);
                }
            }

            // Auto-assign order if it's 0 or null
            if (!isset($validated['orden']) || $validated['orden'] === 0) {
                $activeCount = Subcategoria::where('estado', true)->count();
                $validated['orden'] = $activeCount + 1;
            }

            // Si no se proporciona campo_id o es null, mantener el existente o crear uno nuevo si no tiene
            if (!isset($validated['campo_id']) || $validated['campo_id'] === null || $validated['campo_id'] === '') {
                if ($subcategoria->campo_id) {
                    // Mantener el campo_id existente (eliminar campo_id del array para que no se actualice)
                    unset($validated['campo_id']);
                } else {
                    // Crear un nuevo campo si no tiene uno
                    $defaultCampo = Campo::create([
                        'clave' => 'SUB_' . uniqid(),
                        'nombre' => $validated['nombre'] ?? $subcategoria->nombre,
                        'tipo_calculo' => 'SUM',
                        'estado' => true
                    ]);
                    $validated['campo_id'] = $defaultCampo->id;
                }
            }

            $subcategoria->update($validated);
            $subcategoria->load(['categoria', 'campo']);
            
            Log::info('[SubcategoriaController] update: éxito', ['subcategoria_id' => $subcategoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría actualizada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SubcategoriaController] update: validación fallida', [
                'subcategoria_id' => $subcategoria->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] update: excepción', [
                'subcategoria_id' => $subcategoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified subcategoria from storage (soft delete).
     */
    public function destroy(Subcategoria $subcategoria): JsonResponse
    {
        Log::info('[SubcategoriaController] destroy: petición recibida', [
            'subcategoria_id' => $subcategoria->id,
            'trashed' => $subcategoria->trashed()
        ]);
        
        try {
            if ($subcategoria->trashed()) {
                Log::notice('[SubcategoriaController] destroy: subcategoría ya eliminada', ['subcategoria_id' => $subcategoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'La subcategoría ya fue eliminada'
                ], 404);
            }

            $subcategoria->delete();
            
            Log::info('[SubcategoriaController] destroy: éxito', ['subcategoria_id' => $subcategoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría eliminada correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] destroy: excepción', [
                'subcategoria_id' => $subcategoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted subcategoria.
     */
    public function restore($id): JsonResponse
    {
        Log::info('[SubcategoriaController] restore: petición recibida', ['id' => $id]);
        
        try {
            $subcategoria = Subcategoria::onlyTrashed()->findOrFail($id);
            $subcategoria->restore();
            $subcategoria->load(['categoria', 'campo']);
            
            Log::info('[SubcategoriaController] restore: éxito', ['subcategoria_id' => $subcategoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría restaurada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Previsualizar cambio de categoría para una subcategoría
     */
    public function previewCategoriaChange(Request $request, $id): JsonResponse
    {
        Log::info('[SubcategoriaController] previewCategoriaChange: petición recibida', [
            'id' => $id,
            'data' => $request->all()
        ]);
        
        try {
            $subcategoria = Subcategoria::findOrFail($id);
            
            if ($subcategoria->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede cambiar la categoría de una subcategoría eliminada'
                ], 404);
            }

            $validated = $request->validate([
                'nueva_categoria_id' => 'required|exists:categorias,id|different:' . $subcategoria->categoria_id
            ]);

            $nuevaCategoria = Categoria::findOrFail($validated['nueva_categoria_id']);
            
            Log::info('[SubcategoriaController] previewCategoriaChange: datos de subcategoría', [
                'subcategoria_id' => $subcategoria->id,
                'subcategoria_categoria_id' => $subcategoria->categoria_id,
                'subcategoria_nombre' => $subcategoria->nombre
            ]);

            $subcategoria->load(['categoria', 'campo']);
            $categoriaActual = $subcategoria->categoria;

            Log::info('[SubcategoriaController] previewCategoriaChange: categoría cargada', [
                'categoria_actual' => $categoriaActual ? $categoriaActual->toArray() : null
            ]);

            // Validar que la categoría actual exista (incluso si está soft-deleted)
            if (!$categoriaActual) {
                // Intentar cargar con withTrashed
                $categoriaActual = Categoria::withTrashed()->find($subcategoria->categoria_id);
                Log::info('[SubcategoriaController] previewCategoriaChange: categoría con withTrashed', [
                    'categoria_with_trashed' => $categoriaActual ? $categoriaActual->toArray() : null
                ]);
                if (!$categoriaActual) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La categoría actual de la subcategoría no existe'
                    ], 422);
                }
            }

            // Validar compatibilidad de tipo
            if ($categoriaActual->tipo !== $nuevaCategoria->tipo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La nueva categoría debe ser del mismo tipo (' . $categoriaActual->tipo . ')'
                ], 422);
            }

            // Validar estado de la nueva categoría
            if (!$nuevaCategoria->estado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La nueva categoría no está activa'
                ], 422);
            }

            // Contar gastos activos y soft-deleted
            $gastosActivos = Gasto::where('subcategoria_id', $subcategoria->id)->count();
            $gastosEliminados = Gasto::withTrashed()->where('subcategoria_id', $subcategoria->id)->whereNotNull('deleted_at')->count();
            $totalGastos = $gastosActivos + $gastosEliminados;

            // Validar límite de 50 gastos
            if ($totalGastos > 50) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Esta subcategoría tiene más de 50 gastos asociados. Por favor, contacta a soporte técnico para la migración.',
                    'data' => [
                        'gastos_activos' => $gastosActivos,
                        'gastos_eliminados' => $gastosEliminados,
                        'total_gastos' => $totalGastos
                    ]
                ], 422);
            }

            // Validar compatibilidad de campo
            $warnings = [];
            if ($subcategoria->campo && $nuevaCategoria->campo) {
                if ($subcategoria->campo->tipo_calculo !== $nuevaCategoria->campo->tipo_calculo) {
                    $warnings[] = 'El tipo de cálculo del campo es diferente (' . $subcategoria->campo->tipo_calculo . ' vs ' . $nuevaCategoria->campo->tipo_calculo . ')';
                }
                if ($subcategoria->campo->tipo_resultado !== $nuevaCategoria->campo->tipo_resultado) {
                    $warnings[] = 'El tipo de resultado del campo es diferente';
                }
            }

            Log::info('[SubcategoriaController] previewCategoriaChange: éxito', [
                'subcategoria_id' => $subcategoria->id,
                'categoria_actual' => $categoriaActual->nombre,
                'nueva_categoria' => $nuevaCategoria->nombre,
                'gastos_afectados' => $totalGastos
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Previsualización generada correctamente',
                'data' => [
                    'subcategoria_id' => $subcategoria->id,
                    'subcategoria_nombre' => $subcategoria->nombre,
                    'categoria_actual_id' => $categoriaActual->id,
                    'categoria_actual_nombre' => $categoriaActual->nombre,
                    'nueva_categoria_id' => $nuevaCategoria->id,
                    'nueva_categoria_nombre' => $nuevaCategoria->nombre,
                    'gastos_activos' => $gastosActivos,
                    'gastos_eliminados' => $gastosEliminados,
                    'total_gastos' => $totalGastos,
                    'warnings' => $warnings
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SubcategoriaController] previewCategoriaChange: validación fallida', [
                'subcategoria_id' => $subcategoria->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] previewCategoriaChange: excepción', [
                'subcategoria_id' => $subcategoria->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al previsualizar cambio de categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mover subcategoría a una nueva categoría actualizando los gastos asociados
     */
    public function moveToCategoria(Request $request, $id): JsonResponse
    {
        Log::info('[SubcategoriaController] moveToCategoria: petición recibida', [
            'id' => $id,
            'data' => $request->all()
        ]);
        
        try {
            $subcategoria = Subcategoria::findOrFail($id);
            
            if ($subcategoria->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede cambiar la categoría de una subcategoría eliminada'
                ], 404);
            }

            $validated = $request->validate([
                'nueva_categoria_id' => 'required|exists:categorias,id|different:' . $subcategoria->categoria_id
            ]);

            $nuevaCategoria = Categoria::findOrFail($validated['nueva_categoria_id']);
            $subcategoria->load(['categoria', 'campo']);
            $categoriaActual = $subcategoria->categoria;
            $categoriaActualId = $subcategoria->categoria_id;

            // Validar compatibilidad de tipo
            if ($categoriaActual->tipo !== $nuevaCategoria->tipo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La nueva categoría debe ser del mismo tipo (' . $categoriaActual->tipo . ')'
                ], 422);
            }

            // Validar estado de la nueva categoría
            if (!$nuevaCategoria->estado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La nueva categoría no está activa'
                ], 422);
            }

            // Contar gastos
            $gastosActivos = Gasto::where('subcategoria_id', $subcategoria->id)->count();
            $gastosEliminados = Gasto::withTrashed()->where('subcategoria_id', $subcategoria->id)->whereNotNull('deleted_at')->count();
            $totalGastos = $gastosActivos + $gastosEliminados;

            // Validar límite de 50 gastos
            if ($totalGastos > 50) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Esta subcategoría tiene más de 50 gastos asociados. Por favor, contacta a soporte técnico para la migración.',
                    'data' => [
                        'gastos_activos' => $gastosActivos,
                        'gastos_eliminados' => $gastosEliminados,
                        'total_gastos' => $totalGastos
                    ]
                ], 422);
            }

            // Iniciar transacción
            DB::beginTransaction();

            try {
                // Actualizar gastos activos
                $gastosActualizadosActivos = Gasto::where('subcategoria_id', $subcategoria->id)
                    ->update(['categoria_id' => $nuevaCategoria->id]);

                // Actualizar gastos soft-deleted
                $gastosActualizadosEliminados = Gasto::withTrashed()
                    ->where('subcategoria_id', $subcategoria->id)
                    ->whereNotNull('deleted_at')
                    ->update(['categoria_id' => $nuevaCategoria->id]);

                // Actualizar subcategoría
                $subcategoria->update(['categoria_id' => $nuevaCategoria->id]);
                $subcategoria->load(['categoria', 'campo']);

                DB::commit();

                Log::info('[SubcategoriaController] moveToCategoria: éxito', [
                    'subcategoria_id' => $subcategoria->id,
                    'categoria_anterior_id' => $categoriaActualId,
                    'categoria_nueva_id' => $nuevaCategoria->id,
                    'gastos_activos_actualizados' => $gastosActualizadosActivos,
                    'gastos_eliminados_actualizados' => $gastosActualizadosEliminados,
                    'total_gastos_actualizados' => $gastosActualizadosActivos + $gastosActualizadosEliminados
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Subcategoría movida correctamente a la nueva categoría',
                    'data' => [
                        'subcategoria' => new SubcategoriaResource($subcategoria),
                        'gastos_activos_actualizados' => $gastosActualizadosActivos,
                        'gastos_eliminados_actualizados' => $gastosActualizadosEliminados,
                        'total_gastos_actualizados' => $gastosActualizadosActivos + $gastosActualizadosEliminados
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SubcategoriaController] moveToCategoria: validación fallida', [
                'subcategoria_id' => $subcategoria->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SubcategoriaController] moveToCategoria: excepción', [
                'subcategoria_id' => $subcategoria->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al mover subcategoría a nueva categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
