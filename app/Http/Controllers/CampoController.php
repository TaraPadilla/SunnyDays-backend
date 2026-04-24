<?php

namespace App\Http\Controllers;

use App\Models\Campo;
use App\Http\Resources\CampoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CampoController extends Controller
{
    /**
     * Display a listing of campos.
     */
    public function index(): JsonResponse
    {
        Log::info('[CampoController] index: petición recibida');
        
        try {
            $campos = Campo::where('estado', true)->get();
            
            Log::info('[CampoController] index: éxito', ['total' => $campos->count()]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Campos obtenidos correctamente',
                'data' => CampoResource::collection($campos)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CampoController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('[CampoController] store: petición recibida', [
            'data' => $request->all()
        ]);
        
        try {
            $validated = $request->validate([
                'clave' => 'required|string|max:255|unique:campos,clave',
                'nombre' => 'nullable|string|max:255',
                'tipo_calculo' => 'required|in:SUM,COMPUESTA,MANUAL',
                'formula' => 'nullable|string',
                'estado' => 'nullable|boolean',
                'tipo_resultado' => 'nullable|in:PORCENTAJE,ENTERO,CURRENCY,OTRO'
            ]);

            $campo = Campo::create($validated);
            
            Log::info('[CampoController] store: campo creado', [
                'campo_id' => $campo->id,
                'clave' => $campo->clave
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo creado correctamente',
                'data' => new CampoResource($campo)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[CampoController] store: validación fallida', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[CampoController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('[CampoController] show: petición recibida', [
            'campo_id' => $campo->id,
            'trashed' => $campo->trashed()
        ]);
        
        try {
            if ($campo->trashed()) {
                Log::notice('[CampoController] show: campo eliminado', ['campo_id' => $campo->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'El campo no existe o ha sido eliminado'
                ], 404);
            }

            Log::info('[CampoController] show: éxito', ['campo_id' => $campo->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo obtenido correctamente',
                'data' => new CampoResource($campo)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CampoController] show: excepción', [
                'campo_id' => $campo->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('[CampoController] update: petición recibida', [
            'campo_id' => $campo->id,
            'trashed' => $campo->trashed(),
            'data' => $request->all()
        ]);
        
        try {
            if ($campo->trashed()) {
                Log::notice('[CampoController] update: intento sobre campo eliminado', ['campo_id' => $campo->id]);
                
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
                'estado' => 'nullable|boolean',
                'tipo_resultado' => 'nullable|in:PORCENTAJE,ENTERO,CURRENCY,OTRO'
            ]);

            $campo->update($validated);
            
            Log::info('[CampoController] update: éxito', ['campo_id' => $campo->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo actualizado correctamente',
                'data' => new CampoResource($campo)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[CampoController] update: validación fallida', [
                'campo_id' => $campo->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[CampoController] update: excepción', [
                'campo_id' => $campo->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('[CampoController] destroy: petición recibida', [
            'campo_id' => $campo->id,
            'trashed' => $campo->trashed()
        ]);
        
        try {
            if ($campo->trashed()) {
                Log::notice('[CampoController] destroy: campo ya eliminado', ['campo_id' => $campo->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'El campo ya fue eliminado'
                ], 404);
            }

            $campo->delete();
            
            Log::info('[CampoController] destroy: éxito', ['campo_id' => $campo->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CampoController] destroy: excepción', [
                'campo_id' => $campo->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('[CampoController] restore: petición recibida', ['id' => $id]);
        
        try {
            $campo = Campo::onlyTrashed()->findOrFail($id);
            $campo->restore();
            
            Log::info('[CampoController] restore: éxito', ['campo_id' => $campo->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campo restaurado correctamente',
                'data' => new CampoResource($campo)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[CampoController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar campo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
