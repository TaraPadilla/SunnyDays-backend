<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    /**
     * Procesa el documento enviado desde el frontend y lo manda a n8n.
     */
    public function process(Request $request): JsonResponse
    {
        // 1. Validación estricta del archivo
        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        try {
            $file = $request->file('document');

            // 2. Envío a n8n (Asegúrate de usar la URL de Test o Production según corresponda)
            $n8nWebhookUrl = 'https://alianzapruebas.app.n8n.cloud/webhook-test/procesar-documento';

            $response = Http::attach(
                'data', // Nombre del campo que espera n8n
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post($n8nWebhookUrl);

            // 3. Verificamos si la respuesta es exitosa
            if ($response->successful()) {
                $dataExtraida = $response->json();

                // Aquí podrías hacer validaciones extra, por ejemplo:
                // if (!isset($dataExtraida['amount'])) { ... }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Documento procesado correctamente',
                    'data' => $dataExtraida
                ], 200);
            }

            // 4. Manejo de errores de comunicación con n8n
            return response()->json([
                'status' => 'error',
                'message' => 'El procesador de documentos no respondió correctamente.',
                'details' => $response->body()
            ], 502);

        } catch (\Exception $e) {
            // 5. Log de errores inesperados
            Log::error('Error en ReceiptController: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error interno al procesar el archivo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}