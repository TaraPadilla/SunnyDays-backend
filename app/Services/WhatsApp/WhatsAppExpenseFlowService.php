<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\SubcategoriaController;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppExpenseFlowService
{
    protected $whatsAppMessageService;
    protected $flowHandler;

    public function __construct(
        WhatsAppMessageService $whatsAppMessageService,
        WhatsAppFlowHandler $flowHandler,
        InmuebleController $inmuebleController,
        CategoriaController $categoriaController,
        SubcategoriaController $subcategoriaController
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
                
            case 'SELECTING_VAT':
                $this->flowHandler->handleVATSelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_TOTAL_AMOUNT':
                // En este estado manejar botones de confirmación del total
                if ($buttonId === 'CONFIRM_TOTAL') {
                    // Confirmar el monto calculado y pasar a observaciones
                    $activeSession->update(['estado_actual' => 'SELECTING_OBSERVATIONS']);
                    $this->whatsAppMessageService->sendText($from, 
                        '✅ Monto total confirmado. Ahora ingresa alguna observación o escribe "NO" si no hay:'
                    );
                } elseif ($buttonId === 'MODIFY_TOTAL') {
                    // Pedir nuevo monto total manual
                    $activeSession->update(['estado_actual' => 'SELECTING_TOTAL_AMOUNT_MANUAL']);
                    $this->whatsAppMessageService->sendText($from, 
                        'Por favor, ingresa el monto total del gasto (ej: 119000, 150000):'
                    );
                } else {
                    $this->whatsAppMessageService->sendText($from, 
                        '❌ Opción inválida. Por favor, selecciona Confirmar o Modificar.'
                    );
                }
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
                // Sesión válida, verificar si está completada para iniciar nuevo gasto
                if ($existingSession->estado_actual === 'COMPLETED' || $existingSession->estado_actual === 'CANCELLED') {
                    Log::info('WhatsApp Expense Flow - Sesión finalizada, iniciando nuevo gasto', [
                        'from' => $from,
                        'session_id' => $existingSession->id,
                        'estado_actual' => $existingSession->estado_actual,
                    ]);
                    
                    // Crear nueva sesión y enviar primer paso
                    $this->createNewSessionAndStart($from);
                    return;
                }
                
                // Continuar con el flujo normal
                Log::info('WhatsApp Expense Flow - Continuando sesión existente', [
                    'from' => $from,
                    'session_id' => $existingSession->id,
                    'estado_actual' => $existingSession->estado_actual,
                    'ultimo_mensaje_at' => $existingSession->ultimo_mensaje_at,
                ]);

                // Actualizar timestamp y manejar según estado
                $existingSession->update(['ultimo_mensaje_at' => Carbon::now()]);
                
                if ($existingSession->estado_actual === 'SELECTING_DATE') {
                    // Manejar entrada de fecha (puede ser por botones o texto)
                    if (!is_numeric($messageText) && strlen($messageText) > 2) {
                        // Es texto largo, probablemente una fecha manual
                        $this->flowHandler->handleManualDateInput($from, $messageText, $existingSession);
                    } else {
                        // No es fecha, enviar opciones de fecha
                        $this->flowHandler->sendNextStepMessage($from, $existingSession);
                    }
                } elseif ($existingSession->estado_actual === 'SELECTING_AMOUNT_WITHOUT_VAT') {
                    // Manejar entrada de monto sin IVA
                    $this->flowHandler->handleAmountInput($from, $messageText, $existingSession);
                } elseif ($existingSession->estado_actual === 'SELECTING_VAT') {
                    // Manejar entrada de IVA (puede ser por botones o texto)
                    if (is_numeric($messageText)) {
                        // Es entrada numérica, procesar como IVA manual
                        $this->handleManualVATInput($from, $messageText, $existingSession);
                    } else {
                        // No es numérico, enviar opciones de IVA
                        $this->flowHandler->sendNextStepMessage($from, $existingSession);
                    }
                } elseif ($existingSession->estado_actual === 'SELECTING_TOTAL_AMOUNT_MANUAL') {
                    // Manejar entrada manual de monto total
                    $this->handleManualTotalAmountInput($from, $messageText, $existingSession);
                } elseif ($existingSession->estado_actual === 'SELECTING_TOTAL_AMOUNT') {
                    // Manejar confirmación de monto total
                    $this->flowHandler->handleTotalAmountConfirmation($from, $messageText, $existingSession);
                } elseif ($existingSession->estado_actual === 'SELECTING_OBSERVATIONS') {
                    // Manejar entrada de observaciones
                    $this->flowHandler->handleObservationsInput($from, $messageText, $existingSession);
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

    /**
     * Handle manual VAT input from user
     */
    private function handleManualVATInput(string $from, string $messageText, WhatsAppSession $session): void
    {
        Log::info('WhatsApp Expense Flow - Procesando IVA manual', [
            'from' => $from,
            'message_text' => $messageText,
            'session_id' => $session->id,
        ]);

        // Validar que sea un número válido (monto de IVA)
        $vatAmount = $this->parseAmount($messageText);
        if ($vatAmount === null || $vatAmount < 0) {
            $this->whatsAppMessageService->sendText($from, 
                '❌ Monto de IVA inválido. Ingresa un valor positivo (ej: 19000, 0, 500):'
            );
            return;
        }

        // Calcular monto total y guardar
        $montoSinIva = $session->monto_sin_iva;
        $montoTotal = $montoSinIva + $vatAmount;

        $session->update([
            'iva' => $vatAmount,
            'monto_total' => $montoTotal,
            'estado_actual' => 'SELECTING_TOTAL_AMOUNT',
        ]);

        Log::info('WhatsApp Expense Flow - IVA manual procesado', [
            'from' => $from,
            'session_id' => $session->id,
            'vat_amount' => $vatAmount,
            'total_amount' => $montoTotal,
        ]);

        // Enviar confirmación y solicitar confirmación del total
        $confirmationMessage = "✅ IVA: $" . number_format($vatAmount, 2, ',', '.') . "\n\n" .
                               "💰 *Monto total: $" . number_format($montoTotal, 2, ',', '.') . "*\n\n" .
                               "¿Confirmas este monto total? Responde SI o NO:";
        $this->whatsAppMessageService->sendText($from, $confirmationMessage);
    }

    /**
     * Handle manual total amount input from user
     */
    private function handleManualTotalAmountInput(string $from, string $messageText, WhatsAppSession $session): void
    {
        Log::info('WhatsApp Expense Flow - Procesando monto total manual', [
            'from' => $from,
            'message_text' => $messageText,
            'session_id' => $session->id,
        ]);

        // Validar que sea un número válido (monto total)
        $totalAmount = $this->parseAmount($messageText);
        if ($totalAmount === null || $totalAmount <= 0) {
            $this->whatsAppMessageService->sendText($from, 
                '❌ Monto total inválido. Ingresa un valor positivo (ej: 119000, 150000):'
            );
            return;
        }

        // Guardar el nuevo monto total
        $session->update([
            'monto_total' => $totalAmount,
            'estado_actual' => 'SELECTING_OBSERVATIONS',
        ]);

        Log::info('WhatsApp Expense Flow - Monto total manual procesado', [
            'from' => $from,
            'session_id' => $session->id,
            'total_amount' => $totalAmount,
        ]);

        // Enviar confirmación y solicitar observaciones
        $this->whatsAppMessageService->sendText($from, 
            '✅ Monto total actualizado: $' . number_format($totalAmount, 2, ',', '.') . "\n\n" .
            'Ahora ingresa alguna observación o escribe "NO" si no hay:'
        );
    }

    /**
     * Parse percentage from text input
     */
    private function parsePercentage(string $input): ?float
    {
        // Limpiar y extraer número
        $cleanText = preg_replace('/[^0-9.,]/', '', $input);
        $cleanText = str_replace(',', '.', $cleanText);
        
        if (!is_numeric($cleanText)) {
            return null;
        }
        
        $percentage = (float) $cleanText;
        
        // Validar rango
        if ($percentage < 0 || $percentage > 100) {
            return null;
        }
        
        return $percentage;
    }

    /**
     * Parse amount from string input
     */
    private function parseAmount(string $input): ?float
    {
        // Eliminar caracteres no numéricos excepto punto y coma
        $cleanInput = preg_replace('/[^0-9.,]/', '', $input);
        
        // Reemplazar coma por punto para decimal
        $cleanInput = str_replace(',', '.', $cleanInput);
        
        // Convertir a float
        $amount = (float) $cleanInput;
        
        return $amount > 0 ? $amount : null;
    }
}
