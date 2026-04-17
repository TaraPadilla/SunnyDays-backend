<?php

namespace App\Http\Controllers;

use App\Models\Campo;
use App\Http\Resources\CampoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CampoController extends Controller
{
    /**
     * Display a listing of campos.
     */
    public function index(): JsonResponse
    {
        try {
            $campos = Campo::where('estado', true)->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Campos obtenidos correctamente',
                'data' => CampoResource::collection($campos)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener campos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created campo in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'clave' => 'required|string|max:255|unique:campos,clave',
                'nombre' => 'required|string|max:255',
                'tipo_calculo' => 'required|in:SUM,COMPUESTA,MANUAL',
                'formula' => 'nullable|string',
                'estado' => 'nullable|boolean'
            ]);

            $campo = Campo::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo creado correctamente',
                'data' => new CampoResource($campo)
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
                'message' => 'Error al crear campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified campo.
     */
    public function show(Campo $campo): JsonResponse
    {
        try {
            if ($campo->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El campo no existe o ha sido eliminado'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Campo obtenido correctamente',
                'data' => new CampoResource($campo)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified campo in storage.
     */
    public function update(Request $request, Campo $campo): JsonResponse
    {
        try {
            if ($campo->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar un campo eliminado'
                ], 404);
            }

            $validated = $request->validate([
                'clave' => 'nullable|string|max:255|unique:campos,clave,' . $campo->id,
                'nombre' => 'nullable|string|max:255',
                'tipo_calculo' => 'nullable|in:SUM,COMPUESTA,MANUAL',
                'formula' => 'nullable|string',
                'estado' => 'nullable|boolean'
            ]);

            $campo->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo actualizado correctamente',
                'data' => new CampoResource($campo)
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
                'message' => 'Error al actualizar campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified campo from storage (soft delete).
     */
    public function destroy(Campo $campo): JsonResponse
    {
        try {
            if ($campo->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El campo ya fue eliminado'
                ], 404);
            }

            $campo->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Campo eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted campo.
     */
    public function restore($id): JsonResponse
    {
        try {
            $campo = Campo::onlyTrashed()->findOrFail($id);
            $campo->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'Campo restaurado correctamente',
                'data' => new CampoResource($campo)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
