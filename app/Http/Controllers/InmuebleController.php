<?php

namespace App\Http\Controllers;

use App\Models\Inmueble;
use App\Http\Resources\InmuebleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InmuebleController extends Controller
{
    /**
     * Display a listing of inmuebles.
     */
    public function index(): JsonResponse
    {
        Log::info('[InmuebleController] index: petición recibida');
        
        try {
            $inmuebles = Inmueble::where('estado', true)->get();
            
            Log::info('[InmuebleController] index: éxito', ['total' => $inmuebles->count()]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Inmuebles obtenidos correctamente',
                'data' => InmuebleResource::collection($inmuebles)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener inmuebles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created inmueble in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[InmuebleController] store: petición recibida', [
            'data' => $request->all()
        ]);
        
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'direccion' => 'nullable|string|max:500',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
                'estado' => 'nullable|boolean'
            ]);

            // Procesar imagen si se proporciona
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                $rutaImagen = $imagen->store('inmuebles', 'public');
                $validated['imagen'] = $rutaImagen;
                
                Log::info('[InmuebleController] store: imagen procesada', [
                    'ruta' => $rutaImagen,
                    'nombre_original' => $imagen->getClientOriginalName()
                ]);
            }

            $inmueble = Inmueble::create($validated);
            
            // Generar código automáticamente basado en el ID
            $inmueble->update(['codigo' => 'INM-' . $inmueble->id]);
            
            Log::info('[InmuebleController] store: inmueble creado', [
                'inmueble_id' => $inmueble->id,
                'nombre' => $inmueble->nombre,
                'codigo' => $inmueble->codigo
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble creado correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[InmuebleController] store: validación fallida', [
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified inmueble.
     */
    public function show(Inmueble $inmueble): JsonResponse
    {
        Log::info('[InmuebleController] show: petición recibida', [
            'inmueble_id' => $inmueble->id,
            'trashed' => $inmueble->trashed()
        ]);
        
        try {
            if ($inmueble->trashed()) {
                Log::notice('[InmuebleController] show: inmueble eliminado', ['inmueble_id' => $inmueble->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'El inmueble no existe o ha sido eliminado'
                ], 404);
            }

            Log::info('[InmuebleController] show: éxito', ['inmueble_id' => $inmueble->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble obtenido correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] show: excepción', [
                'inmueble_id' => $inmueble->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified inmueble in storage.
     */
    public function update(Request $request, Inmueble $inmueble): JsonResponse
    {
        Log::info('[InmuebleController] update: petición recibida', [
            'inmueble_id' => $inmueble->id,
            'trashed' => $inmueble->trashed(),
            'data' => $request->all()
        ]);
        
        try {
            if ($inmueble->trashed()) {
                Log::notice('[InmuebleController] update: intento sobre inmueble eliminado', ['inmueble_id' => $inmueble->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar un inmueble eliminado'
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'codigo' => 'nullable|string|max:255|unique:inmuebles,codigo,' . $inmueble->id,
                'direccion' => 'nullable|string|max:500',
                'imagen' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
                'estado' => 'nullable|boolean'
            ]);

            // Procesar imagen si se proporciona
            if ($request->hasFile('imagen')) {
                if ($inmueble->imagen) {
                    \Storage::disk('public')->delete($inmueble->imagen);
                    
                    Log::info('[InmuebleController] update: imagen anterior eliminada', [
                        'ruta_anterior' => $inmueble->imagen
                    ]);
                }
                $imagen = $request->file('imagen');
                $rutaImagen = $imagen->store('inmuebles', 'public');
                $validated['imagen'] = $rutaImagen;
                
                Log::info('[InmuebleController] update: nueva imagen procesada', [
                    'ruta_nueva' => $rutaImagen,
                    'nombre_original' => $imagen->getClientOriginalName()
                ]);
            }

            $inmueble->update($validated);
            
            Log::info('[InmuebleController] update: éxito', ['inmueble_id' => $inmueble->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble actualizado correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[InmuebleController] update: validación fallida', [
                'inmueble_id' => $inmueble->id,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] update: excepción', [
                'inmueble_id' => $inmueble->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified inmueble from storage (soft delete).
     */
    public function destroy(Inmueble $inmueble): JsonResponse
    {
        Log::info('[InmuebleController] destroy: petición recibida', [
            'inmueble_id' => $inmueble->id,
            'trashed' => $inmueble->trashed()
        ]);
        
        try {
            if ($inmueble->trashed()) {
                Log::notice('[InmuebleController] destroy: inmueble ya eliminado', ['inmueble_id' => $inmueble->id]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'El inmueble ya fue eliminado'
                ], 404);
            }

            $inmueble->delete();
            
            Log::info('[InmuebleController] destroy: éxito', ['inmueble_id' => $inmueble->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] destroy: excepción', [
                'inmueble_id' => $inmueble->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted inmueble.
     */
    public function restore($id): JsonResponse
    {
        Log::info('[InmuebleController] restore: petición recibida', ['id' => $id]);
        
        try {
            $inmueble = Inmueble::onlyTrashed()->findOrFail($id);
            $inmueble->restore();
            
            Log::info('[InmuebleController] restore: éxito', ['inmueble_id' => $inmueble->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble restaurado correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[InmuebleController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
