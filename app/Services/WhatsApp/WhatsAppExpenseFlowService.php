<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppExpenseFlowService
{
    protected $whatsAppMessageService;

    public function __construct(WhatsAppMessageService $whatsAppMessageService)
    {
        $this->whatsAppMessageService = $whatsAppMessageService;
    }

    /**
     * Handle incoming message and manage expense registration flow
     */
    public function handleIncomingMessage(array $messageData): void
    {
        $from = $messageData['from'] ?? null;
        $messageText = $messageData['message_text'] ?? null;
        $messageType = $messageData['message_type'] ?? null;
        $buttonId = $messageData['button_id'] ?? null;
        $buttonTitle = $messageData['button_title'] ?? null;

        if (!$from) {
            Log::info('WhatsApp Expense Flow - Mensaje ignorado', [
                'from' => $from,
                'message_type' => $messageType,
                'reason' => 'Sin remitente'
            ]);
            return;
        }

        // Manejar mensajes interactivos (botones)
        if ($messageType === 'interactive' && $buttonId) {
            $this->handleButtonSelection($from, $buttonId, $buttonTitle);
            return;
        }

        // Manejar mensajes de texto (inicio de flujo)
        if ($messageType === 'text') {
            $this->handleTextMessage($from, $messageText);
            return;
        }

        Log::info('WhatsApp Expense Flow - Mensaje ignorado', [
            'from' => $from,
            'message_type' => $messageType,
            'reason' => 'Tipo de mensaje no manejado'
        ]);
    }

    /**
     * Handle button selection from interactive messages
     */
    private function handleButtonSelection(string $from, string $buttonId, string $buttonTitle): void
    {
        Log::info('WhatsApp Expense Flow - Botón seleccionado', [
            'from' => $from,
            'button_id' => $buttonId,
            'button_title' => $buttonTitle,
        ]);

        // Buscar sesión activa
        $activeSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if (!$activeSession) {
            Log::warning('WhatsApp Expense Flow - No hay sesión activa para botón', [
                'from' => $from,
                'button_id' => $buttonId,
                'button_title' => $buttonTitle,
            ]);
            return;
        }

        Log::info('WhatsApp Expense Flow - Selección de botón procesada', [
            'from' => $from,
            'button_id' => $buttonId,
            'button_title' => $buttonTitle,
            'estado_actual' => $activeSession->estado_actual,
            'session_id' => $activeSession->id,
        ]);

        // Por ahora solo logueamos, no avanzamos al siguiente paso
        // TODO: Implementar lógica según el botón seleccionado
    }

    /**
     * Handle text messages (start new flow)
     */
    private function handleTextMessage(string $from, ?string $messageText): void
    {
        Log::info('WhatsApp Expense Flow - Iniciando manejo de mensaje', [
            'from' => $from,
            'message_text' => $messageText,
        ]);

        // Buscar sesión activa existente
        $existingSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if ($existingSession) {
            Log::info('WhatsApp Expense Flow - Reiniciando sesión existente', [
                'from' => $from,
                'previous_session_id' => $existingSession->id,
                'previous_estado' => $existingSession->estado_actual,
            ]);

            // Soft delete de sesión anterior
            $existingSession->delete();
        }

        // Crear nueva sesión
        $newSession = WhatsAppSession::create([
            'wa_id' => $from,
            'estado_actual' => 'STARTED',
            'ultimo_mensaje_at' => Carbon::now(),
        ]);

        Log::info('WhatsApp Expense Flow - Sesión creada', [
            'from' => $from,
            'session_id' => $newSession->id,
            'estado_actual' => $newSession->estado_actual,
        ]);

        // Enviar mensaje inicial con botones
        $this->sendInitialMessage($from);

        // Actualizar estado a SELECTING_DATE
        $newSession->update([
            'estado_actual' => 'SELECTING_DATE',
        ]);

        Log::info('WhatsApp Expense Flow - Estado actualizado a SELECTING_DATE', [
            'from' => $from,
            'session_id' => $newSession->id,
        ]);
    }

    /**
     * Send initial message with date selection buttons
     */
    private function sendInitialMessage(string $to): void
    {
        $body = "Hola, bienvenido a Sunny Days ctg.\n¿Qué fecha deseas usar para registrar el gasto?";
        
        $buttons = [
            [
                'id' => 'TODAY',
                'title' => 'Hoy'
            ],
            [
                'id' => 'OTHER_DATE',
                'title' => 'Otra fecha'
            ]
        ];

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Mensaje inicial enviado', [
                'to' => $to,
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar mensaje inicial', [
                'to' => $to,
            ]);
        }
    }
}
