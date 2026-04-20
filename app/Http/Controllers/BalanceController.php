<?php

namespace App\Http\Controllers;

use App\Http\Resources\BalanceResource;
use App\Http\Requests\BalanceRequest;
use App\Models\Balance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BalanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        Log::info('[BalanceController] index: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'filters' => $request->all(['inmueble_id', 'fecha_corte_from', 'fecha_corte_to'])
        ]);

        try {
            Log::debug('[BalanceController] index: construyendo consulta con filtros');
            $query = Balance::with(['inmueble']);

            // Aplicar filtros si existen
            if ($request->filled('inmueble_id')) {
                $query->where('inmueble_id', $request->inmueble_id);
                Log::debug('[BalanceController] index: aplicando filtro inmueble_id', ['inmueble_id' => $request->inmueble_id]);
            }

            if ($request->filled('fecha_corte_from')) {
                $query->whereDate('fecha_corte', '>=', $request->fecha_corte_from);
                Log::debug('[BalanceController] index: aplicando filtro fecha_corte_from', ['fecha_corte_from' => $request->fecha_corte_from]);
            }

            if ($request->filled('fecha_corte_to')) {
                $query->whereDate('fecha_corte', '<=', $request->fecha_corte_to);
                Log::debug('[BalanceController] index: aplicando filtro fecha_corte_to', ['fecha_corte_to' => $request->fecha_corte_to]);
            }

            $balances = $query->orderBy('fecha_corte', 'desc')->get();

            Log::info('[BalanceController] index: éxito', ['total' => $balances->count()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balances obtenidos correctamente',
                'data' => BalanceResource::collection($balances)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[BalanceController] index: excepción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los balances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BalanceRequest $request): JsonResponse
    {
        Log::info('[BalanceController] store: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'data' => $request->validated()
        ]);

        try {
            $balance = Balance::create($request->validated());

            Log::info('[BalanceController] store: balance creado exitosamente', [
                'balance_id' => $balance->id,
                'inmueble_id' => $balance->inmueble_id,
                'fecha_corte' => $balance->fecha_corte
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance creado correctamente',
                'data' => new BalanceResource($balance)
            ], 201);
        } catch (\Exception $e) {
            Log::error('[BalanceController] store: excepción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        Log::info('[BalanceController] show: petición recibida', [
            'method' => 'GET',
            'balance_id' => $id
        ]);

        try {
            $balance = Balance::with(['inmueble'])->findOrFail($id);

            Log::info('[BalanceController] show: balance encontrado', [
                'balance_id' => $balance->id,
                'inmueble_id' => $balance->inmueble_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance obtenido correctamente',
                'data' => new BalanceResource($balance)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[BalanceController] show: excepción', [
                'error' => $e->getMessage(),
                'balance_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Balance no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BalanceRequest $request, string $id): JsonResponse
    {
        Log::info('[BalanceController] update: petición recibida', [
            'method' => $request->method(),
            'path' => $request->path(),
            'balance_id' => $id,
            'data' => $request->validated()
        ]);

        try {
            $balance = Balance::findOrFail($id);
            $balance->update($request->validated());

            Log::info('[BalanceController] update: balance actualizado exitosamente', [
                'balance_id' => $balance->id,
                'inmueble_id' => $balance->inmueble_id,
                'fecha_corte' => $balance->fecha_corte
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance actualizado correctamente',
                'data' => new BalanceResource($balance)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[BalanceController] update: excepción', [
                'error' => $e->getMessage(),
                'balance_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        Log::info('[BalanceController] destroy: petición recibida', [
            'method' => 'DELETE',
            'balance_id' => $id
        ]);

        try {
            $balance = Balance::findOrFail($id);
            $balanceId = $balance->id;
            $inmuebleId = $balance->inmueble_id;

            $balance->delete();

            Log::info('[BalanceController] destroy: balance eliminado exitosamente', [
                'balance_id' => $balanceId,
                'inmueble_id' => $inmuebleId
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance eliminado correctamente',
                'data' => ['id' => $balanceId]
            ], 200);
        } catch (\Exception $e) {
            Log::error('[BalanceController] destroy: excepción', [
                'error' => $e->getMessage(),
                'balance_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft deleted balance.
     */
    public function restore(string $id): JsonResponse
    {
        Log::info('[BalanceController] restore: petición recibida', [
            'method' => 'POST',
            'balance_id' => $id
        ]);

        try {
            $balance = Balance::withTrashed()->findOrFail($id);
            $balance->restore();

            Log::info('[BalanceController] restore: balance restaurado exitosamente', [
                'balance_id' => $balance->id,
                'inmueble_id' => $balance->inmueble_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Balance restaurado correctamente',
                'data' => new BalanceResource($balance)
            ], 200);
        } catch (\Exception $e) {
            Log::error('[BalanceController] restore: excepción', [
                'error' => $e->getMessage(),
                'balance_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al restaurar el balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
