<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReceiptController extends Controller
{
    
    /**
     * Procesa el documento enviado desde el frontend y lo manda a n8n.
     */
    public function process(Request $request): JsonResponse
    {
        try {
            // 1. Validación estricta del archivo
            $validated = $request->validate([
                'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            $file = $validated['document'];
            
            // 2. Validaciones adicionales del archivo
            if (!$file->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo no es válido o está corrupto.',
                    'error' => 'Invalid file'
                ], 400);
            }

            // 3. Configuración del webhook de n8n
            $n8nWebhookUrl = env('N8N_WEBHOOK_URL');
            
            // 4. Preparación de datos para envío
            $fileContent = file_get_contents($file->getRealPath());
            $fileName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();

            // 5. Envío a n8n con timeout (sin reintentos automáticos)
            $response = Http::timeout(90)
                ->attach('document', $fileContent, $fileName, [
                    'Content-Type' => $mimeType
                ])
                ->post($n8nWebhookUrl);

            // 6. Procesamiento de respuesta exitosa
            if ($response->successful()) {
                $dataExtraida = $response->json();
                
                // 7. Validación de la estructura de respuesta de n8n
                if (!$this->validateN8nResponse($dataExtraida)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La respuesta del procesador no tiene el formato esperado.',
                        'received_data' => $dataExtraida
                    ], 422);
                }

                // 8. Validar si el OCR pudo interpretar el documento (verificar que no todos los campos sean null)
                if ($this->allFieldsAreNull($dataExtraida)) {
                    Log::info('OCR no pudo interpretar el documento', [
                        'file' => $fileName,
                        'response' => $dataExtraida
                    ]);
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'El OCR no pudo interpretar el documento. Por favor, verifique que el documento sea legible e intente nuevamente.',
                        'error_type' => 'ocr_failed',
                        'received_data' => $dataExtraida
                    ], 422);
                }

                // 9. Log de éxito para debugging
                Log::info('Documento procesado exitosamente', [
                    'file' => $fileName,
                    'response' => $dataExtraida
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Documento procesado correctamente',
                    'data' => $dataExtraida
                ], 200);
            }

            // 9. Manejo de errores HTTP específicos
            $statusCode = $response->status();
            $errorMessage = $this->getErrorMessage($statusCode, $response->body());

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'status_code' => $statusCode,
                'details' => $response->body()
            ], $statusCode);

        } catch (ValidationException $e) {
            // 10. Errores de validación
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Error de conexión con n8n
            Log::error('Error de conexión con n8n: ' . $e->getMessage(), [
                'status_code' => $e->getCode(),
                'response' => $e->getResponse()?->body(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo conectar con el servicio de procesamiento de documentos. Por favor, intente más tarde.',
                'error_type' => 'connection_error',
                'details' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ], 503);

        } catch (\Illuminate\Http\Server\RequestException $e) {
            // Error del servidor n8n
            Log::error('Error del servidor n8n: ' . $e->getMessage(), [
                'status_code' => $e->getCode(),
                'response' => $e->getResponse()?->body(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'El servicio de procesamiento de documentos está experimentando problemas. Por favor, intente más tarde.',
                'error_type' => 'server_error',
                'details' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ], 502);

        } catch (\Exception $e) {
            // 11. Log de errores inesperados
            Log::error('Error en ReceiptController: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error interno al procesar el archivo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida que la respuesta de n8n tenga la estructura esperada
     */
    private function validateN8nResponse($data): bool
    {
        // Campos mínimos esperados del servicio n8n
        $requiredFields = ['fecha', 'monto_total'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                Log::warning("Campo requerido faltante en respuesta n8n: {$field}", $data);
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica si todos los campos importantes del OCR son null
     */
    private function allFieldsAreNull($data): bool
    {
        // Campos importantes que deberían tener datos si el OCR funcionó
        $importantFields = [
            'monto_sin_iva',
            'iva', 
            'monto_total',
            'fecha',
            'numero_comprobante',
            'proveedor',
            'descripcion'
        ];

        // Si $data no es un array o está vacío, considerarlo como null
        if (!is_array($data) || empty($data)) {
            return true;
        }

        // Verificar si todos los campos importantes son null
        foreach ($importantFields as $field) {
            // Si encontramos al menos un campo que no es null, el OCR funcionó
            if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                return false;
            }
        }

        // Si llegamos aquí, todos los campos importantes son null
        return true;
    }

    /**
     * Obtiene mensaje de error según el código de estado HTTP
     */
    private function getErrorMessage(int $statusCode, string $body): string
    {
        switch ($statusCode) {
            case 400:
                return 'Solicitud incorrecta al procesador de documentos.';
            case 401:
                return 'No autorizado para acceder al procesador.';
            case 403:
                return 'Acceso prohibido al procesador.';
            case 404:
                return 'Servicio de procesamiento no encontrado.';
            case 408:
                return 'Tiempo de espera agotado al procesar el documento.';
            case 429:
                return 'Demasiadas solicitudes al procesador. Intente más tarde.';
            case 500:
                return 'Error interno del procesador de documentos.';
            case 502:
                return 'El procesador de documentos no está disponible temporalmente.';
            case 503:
                return 'El procesador de documentos no está disponible.';
            default:
                Log::warning("Código de estado HTTP no manejado: {$statusCode}", ['body' => $body]);
                return "Error de comunicación con el procesador (código: {$statusCode}).";
        }
    }
}