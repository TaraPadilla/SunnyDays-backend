<?php

namespace App\Http\Controllers;

use App\Models\SoporteGasto;
use App\Http\Resources\SoporteGastoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SoporteGastoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('[SoporteGastoController] index: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path()
        ]);

        try {
            Log::debug('[SoporteGastoController] index: obteniendo soportes de gastos');
            $query = SoporteGasto::with(['gasto']);

            // Filtrar por gasto_id si se proporciona
            if ($request->filled('gasto_id')) {
                $query->where('gasto_id', $request->gasto_id);
                Log::debug('[SoporteGastoController] index: aplicando filtro gasto_id', ['gasto_id' => $request->gasto_id]);
            }

            $soportes = $query->orderBy('created_at', 'desc')->get();

            if ($soportes->isEmpty()) {
                Log::info('[SoporteGastoController] index: no hay soportes de gastos');

                return response()->json([
                    'status' => 'success',
                    'message' => 'No hay soportes de gastos registrados',
                    'data' => []
                ], 200);
            }

            Log::info('[SoporteGastoController] index: éxito', ['total' => $soportes->count()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soportes de gastos obtenidos correctamente',
                'data' => SoporteGastoResource::collection($soportes)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] index: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los soportes de gastos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a file and return the file path.
     */
    public function uploadFile(Request $request): JsonResponse
    {
        Log::info('[SoporteGastoController] uploadFile: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        try {
            Log::debug('[SoporteGastoController] uploadFile: validando archivo');
            $validated = $request->validate([
                'file' => 'required|file|max:2048', // Max 2MB
            ]);

            $file = $validated['file'];
            
            // Generate unique filename
            $timestamp = time();
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = $timestamp . '_' . str_replace(' ', '_', pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Store file in public storage
            $path = $file->storeAs('soportes_gastos', $filename, 'public');
            
            Log::info('[SoporteGastoController] uploadFile: archivo subido exitosamente', [
                'original_name' => $originalName,
                'stored_path' => $path,
                'size' => $file->getSize(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo subido correctamente',
                'data' => [
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SoporteGastoController] uploadFile: validación fallida', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación del archivo',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] uploadFile: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al subir archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('[SoporteGastoController] store: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'keys' => array_keys($request->all()),
        ]);

        try {
            Log::debug('[SoporteGastoController] store: validando entrada');
            $validated = $request->validate([
                'gasto_id' => 'required|exists:gastos,id',
                'archivo' => 'required|string|max:255',
                'nombre_original' => 'nullable|string|max:255',
                'mime_type' => 'nullable|string|max:100'
            ]);

            Log::debug('[SoporteGastoController] store: validación OK, creando registro');
            $soporte = SoporteGasto::create($validated);
            $soporte->load(['gasto']);

            Log::info('[SoporteGastoController] store: soporte creado', ['soporte_id' => $soporte->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto creado correctamente',
                'data' => new SoporteGastoResource($soporte)
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SoporteGastoController] store: validación fallida', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] store: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SoporteGasto $soporteGasto): JsonResponse
    {
        Log::info('[SoporteGastoController] show: petición recibida', [
            'soporte_id' => $soporteGasto->id,
            'trashed' => $soporteGasto->trashed(),
        ]);

        try {
            if ($soporteGasto->trashed()) {
                Log::notice('[SoporteGastoController] show: soporte eliminado (soft), 404', ['soporte_id' => $soporteGasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'El soporte de gasto no existe o ha sido eliminado'
                ], 404);
            }

            Log::debug('[SoporteGastoController] show: cargando relaciones');
            $soporteGasto->load(['gasto']);

            Log::info('[SoporteGastoController] show: éxito', ['soporte_id' => $soporteGasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto obtenido correctamente',
                'data' => new SoporteGastoResource($soporteGasto)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] show: excepción', [
                'soporte_id' => $soporteGasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SoporteGasto $soporteGasto): JsonResponse
    {
        Log::info('[SoporteGastoController] update: petición recibida', [
            'soporte_id' => $soporteGasto->id,
            'trashed' => $soporteGasto->trashed(),
            'keys' => array_keys($request->all()),
        ]);

        try {
            if ($soporteGasto->trashed()) {
                Log::notice('[SoporteGastoController] update: intento sobre soporte eliminado, 404', ['soporte_id' => $soporteGasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar un soporte de gasto eliminado'
                ], 404);
            }

            Log::debug('[SoporteGastoController] update: validando entrada');
            $validated = $request->validate([
                'gasto_id' => 'required|exists:gastos,id',
                'archivo' => 'required|string|max:255',
                'nombre_original' => 'nullable|string|max:255',
                'mime_type' => 'nullable|string|max:100'
            ]);

            Log::debug('[SoporteGastoController] update: validación OK, persistiendo');
            $soporteGasto->update($validated);
            $soporteGasto->load(['gasto']);

            Log::info('[SoporteGastoController] update: éxito', ['soporte_id' => $soporteGasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto actualizado correctamente',
                'data' => new SoporteGastoResource($soporteGasto)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[SoporteGastoController] update: validación fallida', [
                'soporte_id' => $soporteGasto->id,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] update: excepción', [
                'soporte_id' => $soporteGasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(SoporteGasto $soporteGasto): JsonResponse
    {
        Log::info('[SoporteGastoController] destroy: petición recibida', [
            'soporte_id' => $soporteGasto->id,
            'trashed' => $soporteGasto->trashed(),
        ]);

        try {
            if ($soporteGasto->trashed()) {
                Log::notice('[SoporteGastoController] destroy: ya estaba eliminado, 404', ['soporte_id' => $soporteGasto->id]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'El soporte de gasto ya fue eliminado'
                ], 404);
            }

            Log::debug('[SoporteGastoController] destroy: aplicando soft delete');
            $soporteGasto->delete();

            Log::info('[SoporteGastoController] destroy: éxito', ['soporte_id' => $soporteGasto->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] destroy: excepción', [
                'soporte_id' => $soporteGasto->id ?? null,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted resource.
     */
    public function restore($id): JsonResponse
    {
        Log::info('[SoporteGastoController] restore: petición recibida', [
            'id' => $id,
            'method' => request()->method(),
        ]);

        try {
            Log::debug('[SoporteGastoController] restore: buscando en papelera');
            $soporte = SoporteGasto::onlyTrashed()->findOrFail($id);
            $soporte->restore();
            $soporte->load(['gasto']);

            Log::info('[SoporteGastoController] restore: éxito', ['soporte_id' => $soporte->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto restaurado correctamente',
                'data' => new SoporteGastoResource($soporte)
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::notice('[SoporteGastoController] restore: no encontrado en papelera', ['id' => $id]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] restore: excepción', [
                'id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar soporte de gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
