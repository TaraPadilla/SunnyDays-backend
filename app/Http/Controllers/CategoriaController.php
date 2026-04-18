<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Campo;
use App\Http\Resources\CategoriaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CategoriaController extends Controller
{
    /**
     * Display a listing of categorias.
     */
    public function index(): JsonResponse
    {
        Log::info('[CategoriaController] index: petición recibida');
        
        try {
            $categorias = Categoria::where('estado', true)->with('campo')->get();
            
            Log::info('[CategoriaController] index: éxito', ['total' => $categorias->count()]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Categorías obtenidas correctamente',
                'data' => CategoriaResource::collection($categorias)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener categorías',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created categoria in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[CategoriaController] store: petición recibida', [
            'data' => $request->all()
        ]);
        
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'tipo' => 'required|in:Ingreso,Egreso',
                'visible_sum' => 'nullable|boolean',
                'orden' => 'nullable|integer',
                'campo_id' => 'nullable|exists:campos,id',
                'estado' => 'nullable|boolean'
            ]);

            // Auto-assign order if it's 0 or null
            if (!isset($validated['orden']) || $validated['orden'] === 0) {
                $activeCount = Categoria::where('estado', true)->count();
                $validated['orden'] = $activeCount + 1;
            }

            // Create default field if not provided
            if (!isset($validated['campo_id']) || $validated['campo_id'] === null || $validated['campo_id'] === '') {
                try {
                    $defaultCampo = Campo::create([
                        'clave' => 'SUB_' . ($validated['orden'] ?? 1),
                        'nombre' => $validated['nombre'],
                        'tipo_calculo' => 'SUM',
                        'estado' => true
                    ]);
                    $validated['campo_id'] = $defaultCampo->id;
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle unique constraint violation
                    if ($e->getCode() === 23000 && strpos($e->getMessage(), 'UNIQUE') !== false) {
                        // If clave already exists, try with a different suffix
                        $suffix = 1;
                        do {
                            $newClave = 'SUB_' . ($validated['orden'] ?? 1) . '_' . $suffix;
                            $suffix++;
                        } while (Campo::where('clave', $newClave)->exists());
                        
                        $defaultCampo = Campo::create([
                            'clave' => $newClave,
                            'nombre' => $validated['nombre'],
                            'tipo_calculo' => 'SUM',
                            'estado' => true
                        ]);
                        $validated['campo_id'] = $defaultCampo->id;
                    } else {
                        throw $e;
                    }
                }
            }

            $categoria = Categoria::create($validated);
            $categoria->load('campo');
            
            Log::info('[CategoriaController] store: categoría creada', [
                'categoria_id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'campo_id' => $categoria->campo_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría creada correctamente',
                'data' => new CategoriaResource($categoria)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[CategoriaController] store: validación fallida', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified categoria.
     */
    public function show(Categoria $categoria): JsonResponse
    {
        Log::info('[CategoriaController] show: petición recibida', [
            'categoria_id' => $categoria->id,
            'trashed' => $categoria->trashed()
        ]);
        
        try {
            if ($categoria->trashed()) {
                Log::notice('[CategoriaController] show: categoría eliminada', ['categoria_id' => $categoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'La categoría no existe o ha sido eliminada'
                ], 404);
            }

            $categoria->load('campo');
            
            Log::info('[CategoriaController] show: éxito', ['categoria_id' => $categoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría obtenida correctamente',
                'data' => new CategoriaResource($categoria)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] show: excepción', [
                'categoria_id' => $categoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified categoria in storage.
     */
    public function update(Request $request, Categoria $categoria): JsonResponse
    {
        Log::info('[CategoriaController] update: petición recibida', [
            'categoria_id' => $categoria->id,
            'trashed' => $categoria->trashed(),
            'data' => $request->all()
        ]);
        
        try {
            if ($categoria->trashed()) {
                Log::notice('[CategoriaController] update: intento sobre categoría eliminada', ['categoria_id' => $categoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar una categoría eliminada'
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'tipo' => 'nullable|in:Ingreso,Egreso',
                'visible_sum' => 'nullable|boolean',
                'orden' => 'nullable|integer',
                'campo_id' => 'nullable|exists:campos,id',
                'estado' => 'nullable|boolean'
            ]);

            // Auto-assign order if it's 0 or null
            if (!isset($validated['orden']) || $validated['orden'] === 0) {
                $activeCount = Categoria::where('estado', true)->count();
                $validated['orden'] = $activeCount + 1;
            }

            // Si no se proporciona campo_id o es null, mantener el existente o crear uno nuevo si no tiene
            if (!isset($validated['campo_id']) || $validated['campo_id'] === null || $validated['campo_id'] === '') {
                if ($categoria->campo_id) {
                    // Mantener el campo_id existente (eliminar campo_id del array para que no se actualice)
                    unset($validated['campo_id']);
                } else {
                    // Crear un nuevo campo si no tiene uno
                    try {
                        $defaultCampo = Campo::create([
                            'clave' => 'SUB_' . ($validated['orden'] ?? $categoria->orden ?? 1),
                            'nombre' => $validated['nombre'] ?? $categoria->nombre,
                            'tipo_calculo' => 'SUM',
                            'estado' => true
                        ]);
                        $validated['campo_id'] = $defaultCampo->id;
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Handle unique constraint violation
                        if ($e->getCode() === 23000 && strpos($e->getMessage(), 'UNIQUE') !== false) {
                            // If clave already exists, try with a different suffix
                            $suffix = 1;
                            do {
                                $newClave = 'SUB_' . ($validated['orden'] ?? $categoria->orden ?? 1) . '_' . $suffix;
                                $suffix++;
                            } while (Campo::where('clave', $newClave)->exists());
                            
                            $defaultCampo = Campo::create([
                                'clave' => $newClave,
                                'nombre' => $validated['nombre'] ?? $categoria->nombre,
                                'tipo_calculo' => 'SUM',
                                'estado' => true
                            ]);
                            $validated['campo_id'] = $defaultCampo->id;
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            $categoria->update($validated);
            $categoria->load('campo');
            
            Log::info('[CategoriaController] update: éxito', ['categoria_id' => $categoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría actualizada correctamente',
                'data' => new CategoriaResource($categoria)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[CategoriaController] update: validación fallida', [
                'categoria_id' => $categoria->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] update: excepción', [
                'categoria_id' => $categoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified categoria from storage (soft delete).
     */
    public function destroy(Categoria $categoria): JsonResponse
    {
        Log::info('[CategoriaController] destroy: petición recibida', [
            'categoria_id' => $categoria->id,
            'trashed' => $categoria->trashed()
        ]);
        
        try {
            if ($categoria->trashed()) {
                Log::notice('[CategoriaController] destroy: categoría ya eliminada', ['categoria_id' => $categoria->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'La categoría ya fue eliminada'
                ], 404);
            }

            $categoria->delete();
            
            Log::info('[CategoriaController] destroy: éxito', ['categoria_id' => $categoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría eliminada correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] destroy: excepción', [
                'categoria_id' => $categoria->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted categoria.
     */
    public function restore($id): JsonResponse
    {
        Log::info('[CategoriaController] restore: petición recibida', ['id' => $id]);
        
        try {
            $categoria = Categoria::onlyTrashed()->findOrFail($id);
            $categoria->restore();
            $categoria->load('campo');
            
            Log::info('[CategoriaController] restore: éxito', ['categoria_id' => $categoria->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría restaurada correctamente',
                'data' => new CategoriaResource($categoria)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CategoriaController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
