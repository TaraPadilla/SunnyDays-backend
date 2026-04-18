<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Http\Resources\GastoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GastoController extends Controller
{
    /**
     * Display a listing of gastos.
     */
    public function index(): JsonResponse
    {
        try {
            $gastos = Gasto::with(['inmueble', 'categoria.campo', 'subcategoria.campo'])
                ->orderBy('fecha', 'desc')
                ->get();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Gastos obtenidos correctamente',
                'data' => GastoResource::collection($gastos)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener gastos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created gasto in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the request - works for both n8n processed data and regular form submission
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

            $gasto = Gasto::create($validated);
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto creado correctamente',
                'data' => new GastoResource($gasto)
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
        try {
            if ($gasto->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El gasto no existe o ha sido eliminado'
                ], 404);
            }

            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto obtenido correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Exception $e) {
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
        try {
            if ($gasto->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede actualizar un gasto eliminado'
                ], 404);
            }

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

            $gasto->update($validated);
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto actualizado correctamente',
                'data' => new GastoResource($gasto)
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
        try {
            if ($gasto->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El gasto ya fue eliminado'
                ], 404);
            }

            $gasto->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
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
        try {
            $gasto = Gasto::onlyTrashed()->findOrFail($id);
            $gasto->restore();
            $gasto->load(['inmueble', 'categoria.campo', 'subcategoria.campo']);

            return response()->json([
                'status' => 'success',
                'message' => 'Gasto restaurado correctamente',
                'data' => new GastoResource($gasto)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar gasto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
