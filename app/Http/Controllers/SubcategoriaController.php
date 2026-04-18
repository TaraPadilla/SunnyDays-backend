<?php

namespace App\Http\Controllers;

use App\Models\Subcategoria;
use App\Models\Campo;
use App\Http\Resources\SubcategoriaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubcategoriaController extends Controller
{
    /**
     * Display a listing of subcategorias.
     */
    public function index(): JsonResponse
    {
        try {
            $subcategorias = Subcategoria::where('estado', true)
                ->with(['categoria', 'campo'])
                ->orderBy('orden')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Subcategorías obtenidas correctamente',
                'data' => SubcategoriaResource::collection($subcategorias)
            ], 200);
        } catch (\Exception $e) {
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
                $maxOrden = Subcategoria::withTrashed()->max('orden') ?? 0;
                $validated['orden'] = $maxOrden + 1;
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

            $subcategoria = Subcategoria::create($validated);
            $subcategoria->load(['categoria', 'campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría creada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
        try {
            if ($subcategoria->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La subcategoría no existe o ha sido eliminada'
                ], 404);
            }

            $subcategoria->load(['categoria', 'campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría obtenida correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Exception $e) {
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
        try {
            if ($subcategoria->trashed()) {
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

            // Create default field if not provided and subcategoria doesn't have one
            if ((!isset($validated['campo_id']) || $validated['campo_id'] === null || $validated['campo_id'] === '') && !$subcategoria->campo_id) {
                try {
                    $defaultCampo = Campo::create([
                        'clave' => 'SUB_' . ($validated['orden'] ?? $subcategoria->orden ?? 1),
                        'nombre' => $validated['nombre'] ?? $subcategoria->nombre,
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
                            $newClave = 'SUB_' . ($validated['orden'] ?? $subcategoria->orden ?? 1) . '_' . $suffix;
                            $suffix++;
                        } while (Campo::where('clave', $newClave)->exists());
                        
                        $defaultCampo = Campo::create([
                            'clave' => $newClave,
                            'nombre' => $validated['nombre'] ?? $subcategoria->nombre,
                            'tipo_calculo' => 'SUM',
                            'estado' => true
                        ]);
                        $validated['campo_id'] = $defaultCampo->id;
                    } else {
                        throw $e;
                    }
                }
            }

            $subcategoria->update($validated);
            $subcategoria->load(['categoria', 'campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría actualizada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
        try {
            if ($subcategoria->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La subcategoría ya fue eliminada'
                ], 404);
            }

            $subcategoria->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría eliminada correctamente'
            ], 200);
        } catch (\Exception $e) {
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
        try {
            $subcategoria = Subcategoria::onlyTrashed()->findOrFail($id);
            $subcategoria->restore();
            $subcategoria->load(['categoria', 'campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Subcategoría restaurada correctamente',
                'data' => new SubcategoriaResource($subcategoria)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar subcategoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
