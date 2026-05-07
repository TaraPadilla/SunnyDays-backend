<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppExpenseFlowService;
use Illuminate\Support\Facades\Http;

class WhatsAppWebhookController extends Controller
{
    protected $whatsAppMessageService;
    protected $whatsAppExpenseFlowService;

    public function __construct(
        WhatsAppMessageService $whatsAppMessageService,
        WhatsAppExpenseFlowService $whatsAppExpenseFlowService
    ) {
        $this->whatsAppMessageService = $whatsAppMessageService;
        $this->whatsAppExpenseFlowService = $whatsAppExpenseFlowService;
    }
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
        $expectedToken = config('services.whatsapp.verify_token');

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
            'headers' => [
                'content-type' => $request->header('Content-Type'),
                'content-length' => $request->header('Content-Length'),
                'user-agent' => $request->userAgent(),
            ],
            'json_last_error_msg' => $jsonError,
            'json_last_error' => json_last_error(),
            'ip' => $request->ip(),
        ]);

        // Detectar tipo de evento: mensajes vs status
        $hasMessages = !empty(data_get($payload, 'entry.0.changes.0.value.messages'));
        $hasStatuses = !empty(data_get($payload, 'entry.0.changes.0.value.statuses'));

        if ($hasMessages) {
            Log::info('WhatsApp Webhook - Mensaje entrante detectado');

            // Extraer datos del mensaje
            $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
            $displayPhoneNumber = data_get($payload, 'entry.0.changes.0.value.metadata.display_phone_number');
            $contactName = data_get($payload, 'entry.0.changes.0.value.contacts.0.profile.name');
            $from = data_get($payload, 'entry.0.changes.0.value.messages.0.from');
            $messageId = data_get($payload, 'entry.0.changes.0.value.messages.0.id');
            $messageType = data_get($payload, 'entry.0.changes.0.value.messages.0.type');

            // Extraer datos según el tipo de mensaje
            $messageText = null;
            $buttonId = null;
            $buttonTitle = null;

            if ($messageType === 'text') {
                $messageText = data_get($payload, 'entry.0.changes.0.value.messages.0.text.body');
            } elseif ($messageType === 'interactive') {
                $interactiveType = data_get($payload, 'entry.0.changes.0.value.messages.0.interactive.type');
                if ($interactiveType === 'button_reply') {
                    $buttonId = data_get($payload, 'entry.0.changes.0.value.messages.0.interactive.button_reply.id');
                    $buttonTitle = data_get($payload, 'entry.0.changes.0.value.messages.0.interactive.button_reply.title');
                }
            }

            Log::info('WhatsApp Webhook - Datos del mensaje', [
                'phone_number_id' => $phoneNumberId,
                'display_phone_number' => $displayPhoneNumber,
                'contact_name' => $contactName,
                'from' => $from,
                'message_id' => $messageId,
                'message_type' => $messageType,
                'message_text' => $messageText,
                'button_id' => $buttonId,
                'button_title' => $buttonTitle,
            ]);

            // Delegar manejo del flujo conversacional al servicio especializado
            if ($from && ($messageType === 'text' || $messageType === 'interactive')) {
                if ($messageType === 'text' && $messageText) {
                    $this->whatsAppExpenseFlowService->handleTextMessage($from, $messageText);
                } elseif ($messageType === 'interactive' && $buttonId && $buttonTitle) {
                    $this->whatsAppExpenseFlowService->handleInteractiveMessage($from, $buttonId, $buttonTitle);
                }
            }
        } elseif ($hasStatuses) {
            Log::info('WhatsApp Webhook - Status update detectado');

            // Extraer datos del status
            $status = data_get($payload, 'entry.0.changes.0.value.statuses.0.status');
            $recipientId = data_get($payload, 'entry.0.changes.0.value.statuses.0.recipient_id');
            $statusMessageId = data_get($payload, 'entry.0.changes.0.value.statuses.0.id');

            Log::info('WhatsApp Webhook - Datos del status', [
                'status' => $status,
                'recipient_id' => $recipientId,
                'message_id' => $statusMessageId,
            ]);
        } else {
            Log::info('WhatsApp Webhook - Evento no reconocido', [
                'payload_structure' => array_keys($payload ?? []),
            ]);
        }

        // Responder siempre con éxito para confirmar recepción
        return response()->json(['success' => true], 200);
    }
}
