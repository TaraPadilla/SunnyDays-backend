<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use App\Models\Inmueble;
use App\Models\Categoria;
use App\Models\Subcategoria;
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

        // Implementar lógica según el botón seleccionado y estado actual
        switch ($activeSession->estado_actual) {
            case 'SELECTING_DATE':
                $this->handleDateSelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_PROPERTY':
                $this->handlePropertySelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_CATEGORY':
                $this->handleCategorySelection($from, $buttonId, $activeSession);
                break;
                
            case 'SELECTING_SUBCATEGORY':
                $this->handleSubcategorySelection($from, $buttonId, $activeSession);
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

            // Enviar listado de inmuebles para seleccionar
            $this->sendPropertyList($from);
            
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
     * Handle property selection from buttons
     */
    private function handlePropertySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_PROPERTY') {
            Log::warning('WhatsApp Expense Flow - Botón de propiedad recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'PROPERTY_')) {
            Log::warning('WhatsApp Expense Flow - Botón de propiedad con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Extraer ID del inmueble
        $propertyId = str_replace('PROPERTY_', '', $buttonId);
        
        // Validar que el inmueble exista
        $property = Inmueble::find($propertyId);
        if (!$property) {
            Log::warning('WhatsApp Expense Flow - Inmueble no encontrado', [
                'from' => $from,
                'property_id' => $propertyId,
                'button_id' => $buttonId,
            ]);
            
            $this->whatsAppMessageService->sendText($from, 
                '❌ El inmueble seleccionado no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar inmueble en la sesión
        $session->update([
            'inmueble_id' => $propertyId,
            'estado_actual' => 'SELECTING_CATEGORY',
        ]);

        Log::info('WhatsApp Expense Flow - Inmueble seleccionado', [
            'from' => $from,
            'session_id' => $session->id,
            'property_id' => $propertyId,
            'property_name' => $property->nombre,
        ]);

        // Enviar confirmación y listado de categorías
        $confirmationMessage = "✅ Inmueble seleccionado: {$property->nombre}";
        $this->whatsAppMessageService->sendText($from, $confirmationMessage);
        
        // Enviar listado de categorías (asumimos tipo 'gasto' por ahora)
        $this->sendCategoryList($from, 'gasto');
    }

    /**
     * Handle category selection from buttons
     */
    private function handleCategorySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_CATEGORY') {
            Log::warning('WhatsApp Expense Flow - Botón de categoría recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'CATEGORY_')) {
            Log::warning('WhatsApp Expense Flow - Botón de categoría con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Extraer ID de la categoría
        $categoryId = str_replace('CATEGORY_', '', $buttonId);
        
        // Validar que la categoría exista
        $category = Categoria::find($categoryId);
        if (!$category) {
            Log::warning('WhatsApp Expense Flow - Categoría no encontrada', [
                'from' => $from,
                'category_id' => $categoryId,
                'button_id' => $buttonId,
            ]);
            
            $this->whatsAppMessageService->sendText($from, 
                '❌ La categoría seleccionada no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar categoría en la sesión
        $session->update([
            'tipo_categoria_id' => $categoryId,
            'estado_actual' => 'SELECTING_SUBCATEGORY',
        ]);

        Log::info('WhatsApp Expense Flow - Categoría seleccionada', [
            'from' => $from,
            'session_id' => $session->id,
            'category_id' => $categoryId,
            'category_name' => $category->nombre,
            'category_type' => $category->tipo,
        ]);

        // Enviar confirmación y listado de subcategorías
        $confirmationMessage = "✅ Categoría seleccionada: {$category->nombre}";
        $this->whatsAppMessageService->sendText($from, $confirmationMessage);
        
        // Enviar listado de subcategorías
        $this->sendSubcategoryList($from, $categoryId);
    }

    /**
     * Handle subcategory selection from buttons
     */
    private function handleSubcategorySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_SUBCATEGORY') {
            Log::warning('WhatsApp Expense Flow - Botón de subcategoría recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'SUBCATEGORY_')) {
            Log::warning('WhatsApp Expense Flow - Botón de subcategoría con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Extraer ID de la subcategoría
        $subcategoryId = str_replace('SUBCATEGORY_', '', $buttonId);
        
        // Validar que la subcategoría exista
        $subcategory = Subcategoria::find($subcategoryId);
        if (!$subcategory) {
            Log::warning('WhatsApp Expense Flow - Subcategoría no encontrada', [
                'from' => $from,
                'subcategory_id' => $subcategoryId,
                'button_id' => $buttonId,
            ]);
            
            $this->whatsAppMessageService->sendText($from, 
                '❌ La subcategoría seleccionada no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar subcategoría en la sesión
        $session->update([
            'categoria_gasto_id' => $subcategoryId,
            'estado_actual' => 'SELECTING_AMOUNT',
        ]);

        Log::info('WhatsApp Expense Flow - Subcategoría seleccionada', [
            'from' => $from,
            'session_id' => $session->id,
            'subcategory_id' => $subcategoryId,
            'subcategory_name' => $subcategory->nombre,
        ]);

        // Enviar confirmación y solicitar monto
        $confirmationMessage = "✅ Subcategoría seleccionada: {$subcategory->nombre}\n\n" .
                               "Ahora por favor, ingresa el monto del gasto (solo números):";
        $this->whatsAppMessageService->sendText($from, $confirmationMessage);
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

        // Enviar confirmación y listado de inmuebles
        $successMessage = "✅ Fecha registrada: {$parsedDate->format('d/m/Y')}";
        $this->whatsAppMessageService->sendText($from, $successMessage);
        
        // Enviar listado de inmuebles para seleccionar
        $this->sendPropertyList($from);
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
                // Enviar listado de inmuebles
                $this->sendPropertyList($from);
                break;
                
            case 'SELECTING_CATEGORY':
                // Enviar listado de categorías (asumimos tipo 'gasto')
                $this->sendCategoryList($from, 'gasto');
                break;
                
            case 'SELECTING_SUBCATEGORY':
                // Enviar listado de subcategorías si tenemos categoría seleccionada
                if ($session->tipo_categoria_id) {
                    $this->sendSubcategoryList($from, $session->tipo_categoria_id);
                } else {
                    // Si no hay categoría, regresar a selección de categoría
                    $this->sendCategoryList($from, 'gasto');
                    $session->update(['estado_actual' => 'SELECTING_CATEGORY']);
                }
                break;
                
            case 'SELECTING_AMOUNT':
                // Solicitar monto del gasto
                $this->whatsAppMessageService->sendText($from, 
                    '💰 Por favor, ingresa el monto del gasto (solo números):'
                );
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

    /**
     * Obtener inmuebles activos para mostrar en WhatsApp
     */
    private function getActiveProperties(): array
    {
        try {
            $inmuebles = Inmueble::where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo']);

            Log::info('WhatsApp Expense Flow - Inmuebles obtenidos', [
                'total' => $inmuebles->count()
            ]);

            return $inmuebles->map(function ($inmueble) {
                return [
                    'id' => "PROPERTY_{$inmueble->id}",
                    'title' => $inmueble->nombre,
                    'description' => $inmueble->codigo ? "Código: {$inmueble->codigo}" : null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Expense Flow - Error al obtener inmuebles', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener categorías activas por tipo para mostrar en WhatsApp
     */
    private function getActiveCategoriesByType(string $tipo): array
    {
        try {
            $categorias = Categoria::where('tipo', $tipo)
                ->where('estado', true)
                ->where('visible_combo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            Log::info('WhatsApp Expense Flow - Categorías obtenidas', [
                'tipo' => $tipo,
                'total' => $categorias->count()
            ]);

            return $categorias->map(function ($categoria) {
                return [
                    'id' => "CATEGORY_{$categoria->id}",
                    'title' => $categoria->nombre,
                    'description' => null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Expense Flow - Error al obtener categorías', [
                'tipo' => $tipo,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener subcategorías activas por categoría para mostrar en WhatsApp
     */
    private function getActiveSubcategoriesByCategory(int $categoriaId): array
    {
        try {
            $subcategorias = Subcategoria::where('categoria_id', $categoriaId)
                ->where('estado', true)
                ->where('visible_combo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre']);

            Log::info('WhatsApp Expense Flow - Subcategorías obtenidas', [
                'categoria_id' => $categoriaId,
                'total' => $subcategorias->count()
            ]);

            return $subcategorias->map(function ($subcategoria) {
                return [
                    'id' => "SUBCATEGORY_{$subcategoria->id}",
                    'title' => $subcategoria->nombre,
                    'description' => null
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error('WhatsApp Expense Flow - Error al obtener subcategorías', [
                'categoria_id' => $categoriaId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Enviar listado de inmuebles como botones
     */
    private function sendPropertyList(string $to): void
    {
        $properties = $this->getActiveProperties();

        if (empty($properties)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay inmuebles disponibles en este momento. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay muchos inmuebles, enviar como lista en lugar de botones
        if (count($properties) > 10) {
            $this->sendPropertyAsList($to, $properties);
            return;
        }

        // Enviar como botones si son pocos
        $buttons = array_slice($properties, 0, 3); // Máximo 3 botones por mensaje
        
        $body = "🏢 Selecciona el inmueble donde registraste el gasto:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Listado de inmuebles enviado', [
                'to' => $to,
                'properties_count' => count($properties),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar listado de inmuebles', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Enviar inmuebles como lista (para más de 10 elementos)
     */
    private function sendPropertyAsList(string $to, array $properties): void
    {
        $sections = [];
        $chunks = array_chunk($properties, 10); // Máximo 10 por sección

        foreach ($chunks as $index => $chunk) {
            $rows = array_map(function ($property) {
                return [
                    'id' => $property['id'],
                    'title' => $property['title'],
                    'description' => $property['description'] ?? ''
                ];
            }, $chunk);

            $sections[] = [
                'title' => count($chunks) > 1 ? "Inmuebles " . ($index + 1) : "Inmuebles disponibles",
                'rows' => $rows
            ];
        }

        $body = "🏢 Selecciona el inmueble donde registraste el gasto";
        $buttonText = "Ver inmuebles";

        $response = $this->whatsAppMessageService->sendList($to, $body, $buttonText, $sections);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Lista de inmuebles enviada', [
                'to' => $to,
                'properties_count' => count($properties),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar lista de inmuebles', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Enviar listado de categorías como botones
     */
    private function sendCategoryList(string $to, string $tipo): void
    {
        $categories = $this->getActiveCategoriesByType($tipo);

        if (empty($categories)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay categorías de tipo ' . $tipo . ' disponibles. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay muchas categorías, enviar como lista
        if (count($categories) > 10) {
            $this->sendCategoryAsList($to, $categories, $tipo);
            return;
        }

        // Enviar como botones si son pocas
        $buttons = array_slice($categories, 0, 3);
        
        $body = "📋 Selecciona la categoría del gasto:\n\n" .
                "*Tipo: " . ucfirst($tipo) . "*\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Listado de categorías enviado', [
                'to' => $to,
                'tipo' => $tipo,
                'categories_count' => count($categories),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar listado de categorías', [
                'to' => $to,
                'tipo' => $tipo,
            ]);
        }
    }

    /**
     * Enviar categorías como lista
     */
    private function sendCategoryAsList(string $to, array $categories, string $tipo): void
    {
        $sections = [];
        $chunks = array_chunk($categories, 10);

        foreach ($chunks as $index => $chunk) {
            $rows = array_map(function ($category) {
                return [
                    'id' => $category['id'],
                    'title' => $category['title'],
                    'description' => $category['description'] ?? ''
                ];
            }, $chunk);

            $sections[] = [
                'title' => count($chunks) > 1 ? ucfirst($tipo) . " " . ($index + 1) : ucfirst($tipo) . " disponibles",
                'rows' => $rows
            ];
        }

        $body = "📋 Selecciona la categoría del gasto";
        $buttonText = "Ver categorías";

        $response = $this->whatsAppMessageService->sendList($to, $body, $buttonText, $sections);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Lista de categorías enviada', [
                'to' => $to,
                'tipo' => $tipo,
                'categories_count' => count($categories),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar lista de categorías', [
                'to' => $to,
                'tipo' => $tipo,
            ]);
        }
    }

    /**
     * Enviar listado de subcategorías como botones
     */
    private function sendSubcategoryList(string $to, int $categoriaId): void
    {
        $subcategories = $this->getActiveSubcategoriesByCategory($categoriaId);

        if (empty($subcategories)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay subcategorías disponibles para esta categoría. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay muchas subcategorías, enviar como lista
        if (count($subcategories) > 10) {
            $this->sendSubcategoryAsList($to, $subcategories);
            return;
        }

        // Enviar como botones si son pocas
        $buttons = array_slice($subcategories, 0, 3);
        
        $body = "📝 Selecciona la subcategoría del gasto:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Listado de subcategorías enviado', [
                'to' => $to,
                'categoria_id' => $categoriaId,
                'subcategories_count' => count($subcategories),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar listado de subcategorías', [
                'to' => $to,
                'categoria_id' => $categoriaId,
            ]);
        }
    }

    /**
     * Enviar subcategorías como lista
     */
    private function sendSubcategoryAsList(string $to, array $subcategories): void
    {
        $sections = [];
        $chunks = array_chunk($subcategories, 10);

        foreach ($chunks as $index => $chunk) {
            $rows = array_map(function ($subcategory) {
                return [
                    'id' => $subcategory['id'],
                    'title' => $subcategory['title'],
                    'description' => $subcategory['description'] ?? ''
                ];
            }, $chunk);

            $sections[] = [
                'title' => count($chunks) > 1 ? "Subcategorías " . ($index + 1) : "Subcategorías disponibles",
                'rows' => $rows
            ];
        }

        $body = "📝 Selecciona la subcategoría del gasto";
        $buttonText = "Ver subcategorías";

        $response = $this->whatsAppMessageService->sendList($to, $body, $buttonText, $sections);

        if ($response) {
            Log::info('WhatsApp Expense Flow - Lista de subcategorías enviada', [
                'to' => $to,
                'subcategories_count' => count($subcategories),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Expense Flow - Error al enviar lista de subcategorías', [
                'to' => $to,
            ]);
        }
    }
}
