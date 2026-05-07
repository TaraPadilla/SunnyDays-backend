<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación del webhook de WhatsApp Cloud API
     * Meta requiere este endpoint para verificar la URL del webhook
     */
    public function verify(Request $request): Response
    {
        Log::info('WhatsApp Webhook - Verificación iniciada', [
            'all_query_params' => $request->query(),
            'hub_mode' => $request->query('hub_mode'),
            'hub_verify_token' => $request->query('hub_verify_token'),
            'hub_challenge' => $request->query('hub_challenge'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $hubMode = $request->query('hub_mode');
        $hubVerifyToken = $request->query('hub_verify_token');
        $hubChallenge = $request->query('hub_challenge');
        $expectedToken = env('WHATSAPP_VERIFY_TOKEN');

        // Validar que el modo sea 'subscribe'
        if ($hubMode !== 'subscribe') {
            Log::warning('WhatsApp Webhook - Modo inválido', [
                'expected' => 'subscribe',
                'received' => $hubMode,
            ]);
            return response('Modo inválido', 403);
        }

        // Validar que el token coincida
        if ($hubVerifyToken !== $expectedToken) {
            Log::warning('WhatsApp Webhook - Token de verificación inválido', [
                'expected' => $expectedToken ? '[CONFIGURADO]' : '[NO CONFIGURADO]',
                'received' => $hubVerifyToken ? '[ENVIADO]' : '[NO ENVIADO]',
            ]);
            return response('Token de verificación inválido', 403);
        }

        // Validar que exista el challenge
        if (!$hubChallenge) {
            Log::warning('WhatsApp Webhook - Challenge no proporcionado');
            return response('Challenge no proporcionado', 400);
        }

        Log::info('WhatsApp Webhook - Verificación exitosa', [
            'challenge_length' => strlen($hubChallenge),
        ]);

        // Responder con el challenge en texto plano y status 200
        return response($hubChallenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Recepción de eventos y mensajes del webhook de WhatsApp Cloud API
     * Meta envía aquí todos los eventos: mensajes, cambios de estado, etc.
     */
    public function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        // Leer el payload raw y decodificarlo manualmente
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        $jsonError = json_last_error_msg();

        // Registrar información completa para debugging
        Log::info('WhatsApp Webhook - Evento recibido', [
            'headers' => $request->headers->all(),
            'raw_body' => $rawBody,
            'payload' => $payload,
            'json_last_error_msg' => $jsonError,
            'json_last_error' => json_last_error(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
        ]);

        // Extraer datos básicos del mensaje usando data_get() para evitar errores
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $displayPhoneNumber = data_get($payload, 'entry.0.changes.0.value.metadata.display_phone_number');
        $contactName = data_get($payload, 'entry.0.changes.0.value.contacts.0.profile.name');
        $from = data_get($payload, 'entry.0.changes.0.value.messages.0.from');
        $messageId = data_get($payload, 'entry.0.changes.0.value.messages.0.id');
        $messageType = data_get($payload, 'entry.0.changes.0.value.messages.0.type');
        $messageText = data_get($payload, 'entry.0.changes.0.value.messages.0.text.body');

        // Loguear datos extraídos del mensaje
        Log::info('WhatsApp Webhook - Mensaje extraído', [
            'phone_number_id' => $phoneNumberId,
            'display_phone_number' => $displayPhoneNumber,
            'contact_name' => $contactName,
            'from' => $from,
            'message_id' => $messageId,
            'message_type' => $messageType,
            'message_text' => $messageText,
        ]);

        // Enviar respuesta automática solo si es mensaje de texto y tiene remitente
        if ($from && $messageType === 'text') {
            $phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
            $accessToken = env('WHATSAPP_ACCESS_TOKEN');

            if ($phoneNumberId && $accessToken) {
                $endpoint = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";
                
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $from,
                    'type' => 'text',
                    'text' => [
                        'body' => 'Hola, recibimos tu mensaje.'
                    ]
                ];

                try {
                    $response = Http::withToken($accessToken)
                        ->post($endpoint, $payload);

                    Log::info('WhatsApp Webhook - Respuesta enviada', [
                        'to' => $from,
                        'request_payload' => $payload,
                        'response_status' => $response->status(),
                        'response_body' => $response->body(),
                    ]);
                } catch (\Exception $e) {
                    Log::error('WhatsApp Webhook - Error al enviar respuesta', [
                        'to' => $from,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('WhatsApp Webhook - Configuración incompleta para enviar respuesta', [
                    'phone_number_id_configured' => !empty($phoneNumberId),
                    'access_token_configured' => !empty($accessToken),
                ]);
            }
        }

        // Responder siempre con éxito para confirmar recepción
        return response()->json(['success' => true], 200);
    }
}
