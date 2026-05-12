<?php

namespace App\Http\Controllers;

use App\Models\SoporteGasto;
use App\Http\Resources\SoporteGastoResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SoporteGastoController extends Controller
{
    /**
     * Nombre seguro para entrada dentro del ZIP.
     */
    private function sanitizeZipEntryLeaf(string $name, int $soporteId, string $archivoBasename): string
    {
        $base = $name !== '' ? basename(str_replace('\\', '/', $name)) : $archivoBasename;
        $base = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base) ?? 'archivo';
        $base = trim($base, '._');
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'soporte_'.$soporteId;
        }

        return substr($base, 0, 180);
    }

    /**
     * Descarga un ZIP con todos los soportes en disco de los gastos indicados (p. ej. filtro del listado).
     */
    public function zipPorGastos(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! class_exists(ZipArchive::class)) {
            return response()->json([
                'status' => 'error',
                'message' => 'La extensión ZIP de PHP no está disponible en el servidor.',
            ], 500);
        }

        try {
            $validated = $request->validate([
                'gasto_ids' => 'required|array|min:1|max:500',
                'gasto_ids.*' => 'integer|exists:gastos,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors(),
            ], 422);
        }

        $gastoIds = array_values(array_unique(array_map('intval', $validated['gasto_ids'])));

        $soportes = SoporteGasto::query()
            ->whereIn('gasto_id', $gastoIds)
            ->orderBy('gasto_id')
            ->orderBy('id')
            ->get();

        if ($soportes->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los gastos indicados no tienen archivos de soporte registrados.',
            ], 422);
        }

        $tempDir = storage_path('app/temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipBase = 'soportes_gastos_'.date('Ymd_His').'_'.uniqid('', true).'.zip';
        $zipFullPath = $tempDir.DIRECTORY_SEPARATOR.$zipBase;

        $zip = new ZipArchive;
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Log::error('[SoporteGastoController] zipPorGastos: no se pudo crear ZIP', ['path' => $zipFullPath]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo crear el archivo ZIP.',
            ], 500);
        }

        $disk = Storage::disk('public');
        $added = 0;

        foreach ($soportes as $soporte) {
            $rel = str_replace('\\', '/', trim($soporte->archivo));
            if (str_contains($rel, '..') || ! str_starts_with($rel, 'soportes_gastos/')) {
                continue;
            }
            if (! $disk->exists($rel)) {
                continue;
            }
            $localPath = $disk->path($rel);
            if (! is_readable($localPath)) {
                continue;
            }

            $archivoBasename = basename($rel);
            $leaf = $this->sanitizeZipEntryLeaf(
                (string) ($soporte->nombre_original ?? ''),
                $soporte->id,
                $archivoBasename
            );
            $entry = 'gasto_'.$soporte->gasto_id.'/soporte_'.$soporte->id.'_'.$leaf;
            if ($zip->addFile($localPath, $entry)) {
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            if (is_file($zipFullPath)) {
                @unlink($zipFullPath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró ningún archivo en disco para los soportes de estos gastos.',
            ], 422);
        }

        Log::info('[SoporteGastoController] zipPorGastos: ZIP generado', [
            'gastos' => count($gastoIds),
            'archivos' => $added,
        ]);

        $downloadName = 'soportes_gastos_filtrados_'.date('Y-m-d_His').'.zip';

        return response()->download($zipFullPath, $downloadName)->deleteFileAfterSend(true);
    }

    /**
     * Descarga el archivo de un soporte por la API (misma política CORS que /api; evita fetch cross-origin a /storage).
     */
    public function download(int $id)
    {
        try {
            $soporte = SoporteGasto::query()->findOrFail($id);

            $path = str_replace('\\', '/', trim($soporte->archivo));
            if (str_contains($path, '..') || ! str_starts_with($path, 'soportes_gastos/')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ruta de archivo inválida',
                ], 422);
            }

            if (! Storage::disk('public')->exists($path)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Archivo no encontrado en el servidor',
                ], 404);
            }

            $downloadName = $soporte->nombre_original ?: basename($path);

            return Storage::disk('public')->download($path, $downloadName);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Soporte no encontrado',
            ], 404);
        }
    }

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
                'inmueble_id' => 'nullable|exists:inmuebles,id'
            ]);

            $file = $validated['file'];
            $inmuebleId = $request->input('inmueble_id');
            
            // Generate unique filename
            $timestamp = time();
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = $timestamp . '_' . str_replace(' ', '_', pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Store file in inmueble subfolder if provided, otherwise in main folder
            $folder = $inmuebleId ? "soportes_gastos/inmueble_{$inmuebleId}" : 'soportes_gastos';
            $path = $file->storeAs($folder, $filename, 'public');
            
            Log::info('[SoporteGastoController] uploadFile: archivo subido exitosamente', [
                'original_name' => $originalName,
                'stored_path' => $path,
                'folder' => $folder,
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
     * Ruta canónica bajo el disco public: sin segmentos duplicados (solo hoja basename).
     */
    private function canonicalSoporteArchivoPath(string $relativePath, ?int $inmuebleId): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $leaf = basename($relativePath);

        if ($inmuebleId) {
            return "soportes_gastos/inmueble_{$inmuebleId}/{$leaf}";
        }

        return 'soportes_gastos/'.$leaf;
    }

    /**
     * Alinea el archivo en disco con la carpeta del inmueble del gasto (migración progresiva).
     * Sin inmueble en el gasto: no hace nada. Solo hoja bajo soportes_gastos/inmueble_{id}/ (corrige rutas duplicadas en BD o en disco).
     *
     * @return string clave para contadores (moved|skipped_already_aligned|skipped_no_inmueble|skipped_missing_file|failed)
     */
    private function alignSoporteArchivoToGastoInmueble(SoporteGasto $soporte): string
    {
        $soporte->loadMissing('gasto');
        $gasto = $soporte->gasto;
        if (! $gasto || ! $gasto->inmueble_id) {
            return 'skipped_no_inmueble';
        }

        $inmuebleId = (int) $gasto->inmueble_id;
        $rel = str_replace('\\', '/', trim($soporte->archivo));

        if (str_contains($rel, '..') || ! str_starts_with($rel, 'soportes_gastos/')) {
            Log::warning('[SoporteGastoController] alignSoporte: ruta inválida', [
                'soporte_id' => $soporte->id,
                'archivo' => $rel,
            ]);

            return 'failed';
        }

        $canonicalRel = $this->canonicalSoporteArchivoPath($rel, $inmuebleId);
        $targetDir = "soportes_gastos/inmueble_{$inmuebleId}";

        if ($rel === $canonicalRel) {
            return 'skipped_already_aligned';
        }

        $disk = Storage::disk('public');

        // Archivo ya en la ruta canónica en disco; solo corregir registro (p. ej. ruta duplicada en BD)
        if (! $disk->exists($rel) && $disk->exists($canonicalRel)) {
            $soporte->archivo = $canonicalRel;
            $soporte->save();

            Log::info('[SoporteGastoController] alignSoporte: ruta en BD normalizada (archivo ya en destino)', [
                'soporte_id' => $soporte->id,
                'from' => $rel,
                'to' => $canonicalRel,
            ]);

            return 'moved';
        }

        if (! $disk->exists($rel)) {
            Log::warning('[SoporteGastoController] alignSoporte: archivo no encontrado en disco', [
                'soporte_id' => $soporte->id,
                'archivo' => $rel,
            ]);

            return 'skipped_missing_file';
        }

        $targetRel = $canonicalRel;
        if ($disk->exists($targetRel) && $rel !== $targetRel) {
            $leaf = time().'_'.basename($rel);
            $targetRel = "{$targetDir}/{$leaf}";
        }

        if ($rel === $targetRel) {
            return 'skipped_already_aligned';
        }

        try {
            $disk->makeDirectory($targetDir);
            $disk->move($rel, $targetRel);
        } catch (\Throwable $e) {
            Log::error('[SoporteGastoController] alignSoporte: error al mover', [
                'message' => $e->getMessage(),
                'soporte_id' => $soporte->id,
                'from' => $rel,
                'to' => $targetRel,
            ]);

            return 'failed';
        }

        $soporte->archivo = $targetRel;
        $soporte->save();

        Log::info('[SoporteGastoController] alignSoporte: archivo movido', [
            'soporte_id' => $soporte->id,
            'from' => $rel,
            'to' => $targetRel,
            'inmueble_id' => $inmuebleId,
        ]);

        return 'moved';
    }

    /**
     * Reubica en disco todos los soportes de un gasto según el inmueble actual del gasto (sin subir archivos nuevos).
     */
    public function realignForGasto(Request $request): JsonResponse
    {
        Log::info('[SoporteGastoController] realignForGasto: petición recibida', [
            'keys' => array_keys($request->all()),
        ]);

        try {
            $validated = $request->validate([
                'gasto_id' => 'required|exists:gastos,id',
            ]);

            $gastoId = (int) $validated['gasto_id'];
            $soportes = SoporteGasto::with('gasto')->where('gasto_id', $gastoId)->get();

            $counts = [
                'moved' => 0,
                'skipped_already_aligned' => 0,
                'skipped_no_inmueble' => 0,
                'skipped_missing_file' => 0,
                'failed' => 0,
            ];

            foreach ($soportes as $soporte) {
                $status = $this->alignSoporteArchivoToGastoInmueble($soporte);
                if (isset($counts[$status])) {
                    $counts[$status]++;
                } else {
                    $counts['failed']++;
                }
            }

            Log::info('[SoporteGastoController] realignForGasto: completado', [
                'gasto_id' => $gastoId,
                'counts' => $counts,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Alineación de archivos de soporte completada',
                'data' => [
                    'gasto_id' => $gastoId,
                    'processed' => $soportes->count(),
                    'counts' => $counts,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en la validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('[SoporteGastoController] realignForGasto: excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al alinear archivos de soporte',
                'error' => $e->getMessage(),
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
                'archivo' => 'required|string|max:500',
                'nombre_original' => 'nullable|string|max:255',
                'mime_type' => 'nullable|string|max:100'
            ]);

            $gasto = \App\Models\Gasto::find($validated['gasto_id']);
            $inmuebleId = $gasto ? $gasto->inmueble_id : null;

            $archivoInput = str_replace('\\', '/', trim($validated['archivo']));
            if (str_contains($archivoInput, '..')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ruta de archivo inválida',
                ], 422);
            }

            // Siempre ruta canónica (evita soportes_gastos/inmueble_X/soportes_gastos/...)
            $validated['archivo'] = $this->canonicalSoporteArchivoPath(
                $archivoInput,
                $inmuebleId ? (int) $inmuebleId : null
            );

            Log::debug('[SoporteGastoController] store: ruta archivo normalizada', [
                'archivo' => $validated['archivo'],
                'gasto_id' => $validated['gasto_id'],
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

            $soporteGasto->load(['gasto']);

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
            'keys' => array_keys($request->all()),
        ]);

        try {
            if ($soporteGasto->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El soporte de gasto no existe o ha sido eliminado'
                ], 404);
            }

            $validated = $request->validate([
                'nombre_original' => 'nullable|string|max:255',
                'mime_type' => 'nullable|string|max:100',
                'relocate_to_gasto_inmueble' => 'sometimes|boolean',
            ]);

            if (! empty($validated['relocate_to_gasto_inmueble'])) {
                $this->alignSoporteArchivoToGastoInmueble($soporteGasto);
                $soporteGasto->refresh();
            }

            unset($validated['relocate_to_gasto_inmueble']);
            $payload = array_filter($validated, fn ($v) => $v !== null);
            if ($payload !== []) {
                $soporteGasto->update($payload);
            }

            $soporteGasto->load(['gasto']);

            return response()->json([
                'status' => 'success',
                'message' => 'Soporte de gasto actualizado correctamente',
                'data' => new SoporteGastoResource($soporteGasto)
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
