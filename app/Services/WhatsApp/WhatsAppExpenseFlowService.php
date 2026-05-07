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

        // Validar timeout de 2 horas
        if ($this->isSessionExpired($activeSession)) {
            Log::info('WhatsApp Expense Flow - Sesión expirada por timeout en botón', [
                'from' => $from,
                'session_id' => $activeSession->id,
                'ultimo_mensaje_at' => $activeSession->ultimo_mensaje_at,
                'hours_diff' => $activeSession->ultimo_mensaje_at ? $activeSession->ultimo_mensaje_at->diffInHours(now()) : 'N/A (null timestamp)',
            ]);

            // Cancelar sesión existente por timeout
            $activeSession->update([
                'estado_actual' => 'CANCELLED',
                'completed_at' => now(),
            ]);

            // Enviar mensaje de timeout y reiniciar
            $this->whatsAppMessageService->sendText($from, '⏰ Tu sesión ha expirado por inactividad (más de 2 horas). Vamos a empezar de nuevo.');
            $this->createNewSessionAndStart($from);
            return;
        }

        Log::info('WhatsApp Expense Flow - Selección de botón procesada', [
            'from' => $from,
            'button_id' => $buttonId,
            'button_title' => $buttonTitle,
            'estado_actual' => $activeSession->estado_actual,
            'session_id' => $activeSession->id,
        ]);

        // Actualizar timestamp
        $activeSession->update(['ultimo_mensaje_at' => Carbon::now()]);

        // Implementar lógica según el botón seleccionado
        $this->handleDateSelection($from, $buttonId, $activeSession);
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

        // Validar si el usuario quiere cancelar
        if ($this->isCancelCommand($messageText)) {
            $this->handleCancelCommand($from);
            return;
        }

        // Verificar si estamos esperando fecha manual
        $activeSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if ($activeSession && $activeSession->estado_actual === 'SELECTING_DATE_MANUAL') {
            $this->handleManualDateInput($from, $messageText, $activeSession);
            return;
        }

        // Buscar sesión existente que no esté cancelada
        $existingSession = WhatsAppSession::where('wa_id', $from)
            ->where('estado_actual', '!=', 'CANCELLED')
            ->whereNull('completed_at')
            ->first();

        if ($existingSession) {
            // Validar timeout de 2 horas
            if ($this->isSessionExpired($existingSession)) {
                Log::info('WhatsApp Expense Flow - Sesión expirada por timeout', [
                    'from' => $from,
                    'session_id' => $existingSession->id,
                    'ultimo_mensaje_at' => $existingSession->ultimo_mensaje_at,
                    'hours_diff' => $existingSession->ultimo_mensaje_at ? $existingSession->ultimo_mensaje_at->diffInHours(now()) : 'N/A (null timestamp)',
                ]);

                // Cancelar sesión existente por timeout
                $existingSession->update([
                    'estado_actual' => 'CANCELLED',
                    'completed_at' => now(),
                ]);

                // Crear nueva sesión y enviar primer paso
                $this->createNewSessionAndStart($from);
            } else {
                // Sesión válida, continuar con el flujo
                Log::info('WhatsApp Expense Flow - Continuando sesión existente', [
                    'from' => $from,
                    'session_id' => $existingSession->id,
                    'estado_actual' => $existingSession->estado_actual,
                    'ultimo_mensaje_at' => $existingSession->ultimo_mensaje_at,
                ]);

                // Actualizar timestamp y enviar mensaje correspondiente al siguiente paso
                $existingSession->update(['ultimo_mensaje_at' => Carbon::now()]);
                $this->sendNextStepMessage($from, $existingSession);
            }
        } else {
            // No hay sesión existente, crear nueva
            $this->createNewSessionAndStart($from);
        }
    }

    /**
     * Check if the message is a cancel command
     */
    private function isCancelCommand(?string $messageText): bool
    {
        if (!$messageText) {
            return false;
        }

        // Normalizar el texto: quitar espacios y convertir a minúsculas
        $normalizedText = strtolower(trim($messageText));
        
        // Verificar si es "cancelar" o variaciones
        return $normalizedText === 'cancelar';
    }

    /**
     * Handle cancel command
     */
    private function handleCancelCommand(string $from): void
    {
        Log::info('WhatsApp Expense Flow - Comando CANCELAR detectado', [
            'from' => $from,
        ]);

        // Buscar sesión activa
        $activeSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if (!$activeSession) {
            Log::warning('WhatsApp Expense Flow - No hay sesión activa para cancelar', [
                'from' => $from,
            ]);
            
            // Enviar mensaje de confirmación aunque no haya sesión activa
            $this->whatsAppMessageService->sendText($from, 'No hay ningún proceso activo para cancelar.\n\nSi deseas registrar un nuevo gasto, simplemente envíame un mensaje.');
            return;
        }

        // Actualizar estado a CANCELLED
        $activeSession->update([
            'estado_actual' => 'CANCELLED',
            'completed_at' => now(),
        ]);

        Log::info('WhatsApp Expense Flow - Sesión cancelada', [
            'from' => $from,
            'session_id' => $activeSession->id,
            'previous_estado' => $activeSession->getOriginal('estado_actual'),
        ]);

        // Enviar mensaje de confirmación
        $this->whatsAppMessageService->sendText($from, '✅ Proceso cancelado exitosamente.\n\nSi deseas registrar un nuevo gasto, simplemente envíame un mensaje.');
    }

    /**
     * Handle date selection from buttons
     */
    private function handleDateSelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_DATE') {
            Log::warning('WhatsApp Expense Flow - Botón de fecha recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        if ($buttonId === 'TODAY') {
            // Guardar fecha de hoy
            $session->update([
                'fecha_gasto' => now()->toDateString(),
                'estado_actual' => 'SELECTING_PROPERTY',
            ]);

            Log::info('WhatsApp Expense Flow - Fecha hoy guardada', [
                'from' => $from,
                'session_id' => $session->id,
                'fecha_gasto' => $session->fecha_gasto,
            ]);

            // TODO: Enviar mensaje para seleccionar propiedad
            $this->whatsAppMessageService->sendText($from, '✅ Fecha registrada: hoy\n\nAhora selecciona la propiedad del gasto...');
            
        } elseif ($buttonId === 'OTHER_DATE') {
            // Pedir fecha específica
            $session->update(['estado_actual' => 'SELECTING_DATE_MANUAL']);

            Log::info('WhatsApp Expense Flow - Solicitando fecha manual', [
                'from' => $from,
                'session_id' => $session->id,
            ]);

            $message = "Por favor, escribe la fecha del gasto en formato DD/MM/AAAA\n\nEjemplo: 25/12/2023";
            $this->whatsAppMessageService->sendText($from, $message);
            
        } else {
            Log::warning('WhatsApp Expense Flow - Botón de fecha no reconocido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
        }
    }

    /**
     * Handle manual date input from user
     */
    private function handleManualDateInput(string $from, ?string $messageText, WhatsAppSession $session): void
    {
        Log::info('WhatsApp Expense Flow - Procesando fecha manual', [
            'from' => $from,
            'message_text' => $messageText,
            'session_id' => $session->id,
        ]);

        // Validar y transformar la fecha
        $parsedDate = $this->parseAndValidateDate($messageText);

        if (!$parsedDate) {
            Log::warning('WhatsApp Expense Flow - Fecha inválida', [
                'from' => $from,
                'message_text' => $messageText,
            ]);

            $errorMessage = "❌ Formato de fecha inválido.\n\nPor favor, usa el formato DD/MM/AAAA\n\nEjemplos válidos:\n• 25/12/2023\n• 01/01/2024\n• 15-06-2023";
            $this->whatsAppMessageService->sendText($from, $errorMessage);
            return;
        }

        // Guardar fecha válida
        $session->update([
            'fecha_gasto' => $parsedDate->toDateString(),
            'estado_actual' => 'SELECTING_PROPERTY',
        ]);

        Log::info('WhatsApp Expense Flow - Fecha manual guardada', [
            'from' => $from,
            'session_id' => $session->id,
            'fecha_gasto' => $parsedDate->toDateString(),
            'fecha_original' => $messageText,
        ]);

        $successMessage = "✅ Fecha registrada: {$parsedDate->format('d/m/Y')}\n\nAhora selecciona la propiedad del gasto...";
        $this->whatsAppMessageService->sendText($from, $successMessage);
    }

    /**
     * Parse and validate date in various formats
     */
    private function parseAndValidateDate(?string $dateText): ?\Carbon\Carbon
    {
        if (!$dateText) {
            return null;
        }

        // Limpiar el texto: quitar espacios extra
        $cleanText = trim($dateText);

        // Patrones a probar
        $formats = [
            'd/m/Y',  // 25/12/2023
            'd-m-Y',  // 25-12-2023
            'd/m/y',  // 25/12/23
            'd-m-y',  // 25-12-23
        ];

        foreach ($formats as $format) {
            try {
                $date = \Carbon\Carbon::createFromFormat($format, $cleanText);
                
                // Validar que la fecha sea razonable (no muy antigua ni muy futura)
                $now = now();
                $minDate = $now->copy()->subYears(2);
                $maxDate = $now->copy()->addMonths(1);

                if ($date->between($minDate, $maxDate)) {
                    return $date;
                }
            } catch (\Exception $e) {
                // Continuar con el siguiente formato
                continue;
            }
        }

        return null;
    }

    /**
     * Send initial message with date selection buttons
     */
    private function sendInitialMessage(string $to): void
    {
        $body = "Hola, bienvenido a Sunny Days ctg.\n¿Qué fecha deseas usar para registrar el gasto?\n\n*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";
        
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

    /**
     * Check if a session has expired (more than 2 hours since last message)
     */
    private function isSessionExpired(WhatsAppSession $session): bool
    {
        if (!$session->ultimo_mensaje_at) {
            return true; // Si no tiene timestamp, considerar expirada
        }

        return $session->ultimo_mensaje_at->diffInHours(now()) > 2;
    }

    /**
     * Create new session and send initial message
     */
    private function createNewSessionAndStart(string $from): void
    {
        // Crear nueva sesión
        $newSession = WhatsAppSession::create([
            'wa_id' => $from,
            'estado_actual' => 'STARTED',
            'ultimo_mensaje_at' => Carbon::now(),
        ]);

        Log::info('WhatsApp Expense Flow - Nueva sesión creada', [
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
     * Send next step message based on current session state
     */
    private function sendNextStepMessage(string $from, WhatsAppSession $session): void
    {
        switch ($session->estado_actual) {
            case 'SELECTING_DATE':
                // Reenviar mensaje de selección de fecha
                $this->sendInitialMessage($from);
                break;
                
            case 'SELECTING_DATE_MANUAL':
                // Reenviar solicitud de fecha manual
                $message = "Por favor, escribe la fecha del gasto en formato DD/MM/AAAA\n\nEjemplo: 25/12/2023";
                $this->whatsAppMessageService->sendText($from, $message);
                break;
                
            case 'SELECTING_PROPERTY':
                // TODO: Enviar mensaje para seleccionar propiedad
                $this->whatsAppMessageService->sendText($from, '✅ Continuemos...\n\nAhora selecciona la propiedad del gasto...');
                break;
                
            default:
                // Para cualquier otro estado, reiniciar el flujo
                Log::info('WhatsApp Expense Flow - Reiniciando flujo por estado no manejado', [
                    'from' => $from,
                    'estado_actual' => $session->estado_actual,
                ]);
                $this->sendInitialMessage($from);
                $session->update(['estado_actual' => 'SELECTING_DATE']);
                break;
        }
    }
}
