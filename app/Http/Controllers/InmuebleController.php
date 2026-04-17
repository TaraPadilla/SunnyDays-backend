<?php

namespace App\Http\Controllers;

use App\Models\Inmueble;
use App\Http\Resources\InmuebleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InmuebleController extends Controller
{
    /**
     * Display a listing of inmuebles.
     */
    public function index(): JsonResponse
    {
        try {
            $inmuebles = Inmueble::where('estado', true)->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Inmuebles obtenidos correctamente',
                'data' => InmuebleResource::collection($inmuebles)
            ], 200);
        } catch (\Exception $e) {
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
            }

            $inmueble = Inmueble::create($validated);
            
            // Generar código automáticamente basado en el ID
            $inmueble->update(['codigo' => 'INM-' . $inmueble->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble creado correctamente',
                'data' => new InmuebleResource($inmueble)
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
        try {
            if ($inmueble->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El inmueble no existe o ha sido eliminado'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble obtenido correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 200);
        } catch (\Exception $e) {
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
        try {
            if ($inmueble->trashed()) {
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
                }
                $imagen = $request->file('imagen');
                $rutaImagen = $imagen->store('inmuebles', 'public');
                $validated['imagen'] = $rutaImagen;
            }

            $inmueble->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble actualizado correctamente',
                'data' => new InmuebleResource($inmueble)
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
        try {
            if ($inmueble->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El inmueble ya fue eliminado'
                ], 404);
            }

            $inmueble->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
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
        try {
            $inmueble = Inmueble::onlyTrashed()->findOrFail($id);
            $inmueble->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'Inmueble restaurado correctamente',
                'data' => new InmuebleResource($inmueble)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar inmueble',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
