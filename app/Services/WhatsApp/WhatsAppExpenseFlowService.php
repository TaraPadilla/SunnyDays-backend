<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppExpenseFlowService
{
    protected $whatsAppMessageService;
    protected $flowHandler;

    public function __construct(
        WhatsAppMessageService $whatsAppMessageService,
        WhatsAppFlowHandler $flowHandler
    ) {
        $this->whatsAppMessageService = $whatsAppMessageService;
        $this->flowHandler = $flowHandler;
    }

    /**
     * Handle incoming interactive messages (buttons, lists)
     */
    public function handleInteractiveMessage(string $from, string $buttonId, string $buttonTitle): void
    {
        Log::info('WhatsApp Expense Flow - Mensaje interactivo recibido', [
            'from' => $from,
            'button_id' => $buttonId,
            'button_title' => $buttonTitle,
        ]);

        // Buscar sesión activa
        $activeSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if (!$activeSession) {
            Log::warning('WhatsApp Expense Flow - Botón recibido sin sesión activa', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            
            $this->whatsAppMessageService->sendText($from, 
                '❌ No hay ningún proceso activo. Por favor, envía un mensaje para iniciar el registro de un gasto.'
            );
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

        // Actualizar timestamp
        $activeSession->update(['ultimo_mensaje_at' => Carbon::now()]);

        // Implementar lógica según el botón seleccionado y estado actual
        switch ($activeSession->estado_actual) {
            case 'SELECTING_DATE':
                $this->flowHandler->handleDateSelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_PROPERTY':
                $this->flowHandler->handlePropertySelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_CATEGORY':
                $this->flowHandler->handleCategorySelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_SUBCATEGORY':
                $this->flowHandler->handleSubcategorySelection($from, $buttonId, $activeSession);
                break;
                
            default:
                Log::warning('WhatsApp Expense Flow - Botón recibido en estado no manejado', [
                    'from' => $from,
                    'button_id' => $buttonId,
                    'estado_actual' => $activeSession->estado_actual,
                ]);
                break;
        }
    }

    /**
     * Handle text messages (start new flow or manual input)
     */
    public function handleTextMessage(string $from, ?string $messageText): void
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

                // Actualizar timestamp y manejar según estado
                $existingSession->update(['ultimo_mensaje_at' => Carbon::now()]);
                
                if ($existingSession->estado_actual === 'SELECTING_DATE_MANUAL') {
                    // Manejar entrada manual de fecha
                    $this->flowHandler->handleManualDateInput($from, $messageText, $existingSession);
                } elseif ($existingSession->estado_actual === 'SELECTING_AMOUNT') {
                    // TODO: Implementar manejo de monto
                    $this->whatsAppMessageService->sendText($from, 
                        '💰 Monto recibido. Próximamente implementaremos el registro final del gasto.'
                    );
                } else {
                    // Para otros estados, enviar mensaje correspondiente
                    $this->flowHandler->sendNextStepMessage($from, $existingSession);
                }
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

        $cancelVariants = ['cancelar', 'cancel', 'cancelado', 'cancelada', 'salir', 'exit'];
        $cleanText = strtolower(trim($messageText));

        foreach ($cancelVariants as $variant) {
            if (str_contains($cleanText, $variant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle cancel command
     */
    private function handleCancelCommand(string $from): void
    {
        Log::info('WhatsApp Expense Flow - Comando de cancelar recibido', [
            'from' => $from,
        ]);

        $activeSession = WhatsAppSession::where('wa_id', $from)
            ->whereNull('completed_at')
            ->first();

        if (!$activeSession) {
            $this->whatsAppMessageService->sendText($from, 'No hay ningún proceso activo para cancelar.\n\nSi deseas registrar un nuevo gasto, simplemente envíame un mensaje.');
            return;
        }

        $activeSession->update([
            'estado_actual' => 'CANCELLED',
            'completed_at' => now(),
        ]);

        Log::info('WhatsApp Expense Flow - Sesión cancelada', [
            'from' => $from,
            'session_id' => $activeSession->id,
        ]);

        // Enviar mensaje de confirmación
        $this->whatsAppMessageService->sendText($from, '✅ Proceso cancelado exitosamente.\n\nSi deseas registrar un nuevo gasto, simplemente envíame un mensaje.');
    }

    /**
     * Check if session has expired (more than 2 hours since last message)
     */
    private function isSessionExpired(WhatsAppSession $session): bool
    {
        if (!$session->ultimo_mensaje_at) {
            return true;
        }
        return $session->ultimo_mensaje_at->diffInHours(now()) > 2;
    }

    /**
     * Create new session and start the flow
     */
    private function createNewSessionAndStart(string $from): void
    {
        $newSession = WhatsAppSession::create([
            'wa_id' => $from,
            'estado_actual' => 'STARTED',
            'ultimo_mensaje_at' => Carbon::now(),
        ]);

        Log::info('WhatsApp Expense Flow - Nueva sesión creada', [
            'from' => $from,
            'session_id' => $newSession->id,
        ]);

        // Enviar mensaje inicial y actualizar estado
        $this->flowHandler->sendInitialMessage($from);
        $newSession->update(['estado_actual' => 'SELECTING_DATE']);
    }
}
