<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use App\Models\Inmueble;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\CategoriaController;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppFlowHandler
{
    protected $dataService;
    protected $listService;
    protected $messageService;

    public function __construct(
        WhatsAppDataService $dataService,
        WhatsAppListService $listService,
        WhatsAppMessageService $messageService
    ) {
        $this->dataService = $dataService;
        $this->listService = $listService;
        $this->messageService = $messageService;
    }

    /**
     * Handle date selection from buttons
     */
    public function handleDateSelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_DATE') {
            Log::warning('WhatsApp Flow Handler - Botón de fecha recibido en estado incorrecto', [
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

            Log::info('WhatsApp Flow Handler - Fecha hoy guardada', [
                'from' => $from,
                'session_id' => $session->id,
                'fecha_gasto' => $session->fecha_gasto,
            ]);

            // Enviar listado de inmuebles para seleccionar
            $properties = $this->dataService->getActiveProperties();
            $this->listService->sendPropertyList($from, $properties);
            
        } elseif ($buttonId === 'OTHER_DATE') {
            // Pedir fecha específica - mantenerse en SELECTING_DATE
            Log::info('WhatsApp Flow Handler - Solicitando fecha manual', [
                'from' => $from,
                'session_id' => $session->id,
            ]);

            $this->messageService->sendText($from, 
                '📅 Por favor, ingresa la fecha del gasto (formato: DD/MM/YYYY o YYYY-MM-DD):'
            );
            
        } else {
            Log::warning('WhatsApp Flow Handler - Botón de fecha no reconocido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
        }
    }

    /**
     * Handle property selection from buttons
     */
    public function handlePropertySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_PROPERTY') {
            Log::warning('WhatsApp Flow Handler - Botón de propiedad recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'PROPERTY_')) {
            Log::warning('WhatsApp Flow Handler - Botón de propiedad con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Validar y obtener inmueble
        $property = $this->dataService->getValidProperty($buttonId);
        if (!$property) {
            $this->messageService->sendText($from, 
                '❌ El inmueble seleccionado no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar inmueble en la sesión
        $session->update([
            'inmueble_id' => $property->id,
            'estado_actual' => 'SELECTING_CATEGORY',
        ]);

        Log::info('WhatsApp Flow Handler - Inmueble seleccionado', [
            'from' => $from,
            'session_id' => $session->id,
            'property_id' => $property->id,
            'property_name' => $property->nombre,
        ]);

        // Enviar confirmación y listado de categorías
        $confirmationMessage = "✅ Inmueble seleccionado: {$property->nombre}";
        $this->messageService->sendText($from, $confirmationMessage);
        
        // Enviar listado de categorías (filtrado por tipo Egreso)
        $categories = $this->dataService->getActiveCategoriesByType('Egreso');
        $this->listService->sendCategoryList($from, $categories, 'Gastos');
    }

    /**
     * Handle category selection from buttons
     */
    public function handleCategorySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_CATEGORY') {
            Log::warning('WhatsApp Flow Handler - Botón de categoría recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'CATEGORY_')) {
            Log::warning('WhatsApp Flow Handler - Botón de categoría con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Validar y obtener categoría
        $category = $this->dataService->getValidCategory($buttonId);
        if (!$category) {
            $this->messageService->sendText($from, 
                '❌ La categoría seleccionada no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar categoría en la sesión
        $session->update([
            'tipo_categoria_id' => $category->id,
            'estado_actual' => 'SELECTING_SUBCATEGORY',
        ]);

        Log::info('WhatsApp Flow Handler - Categoría seleccionada', [
            'from' => $from,
            'session_id' => $session->id,
            'category_id' => $category->id,
            'category_name' => $category->nombre,
            'category_type' => $category->tipo,
        ]);

        // Enviar confirmación y listado de subcategorías
        $confirmationMessage = "✅ Categoría seleccionada: {$category->nombre}";
        $this->messageService->sendText($from, $confirmationMessage);
        
        // Enviar listado de subcategorías
        $subcategories = $this->dataService->getActiveSubcategoriesByCategory($category->id);
        $this->listService->sendSubcategoryList($from, $subcategories);
    }

    /**
     * Handle subcategory selection from buttons
     */
    public function handleSubcategorySelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_SUBCATEGORY') {
            Log::warning('WhatsApp Flow Handler - Botón de subcategoría recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el botón tenga el formato esperado
        if (!str_starts_with($buttonId, 'SUBCATEGORY_')) {
            Log::warning('WhatsApp Flow Handler - Botón de subcategoría con formato inválido', [
                'from' => $from,
                'button_id' => $buttonId,
            ]);
            return;
        }

        // Validar y obtener subcategoría
        $subcategory = $this->dataService->getValidSubcategory($buttonId);
        if (!$subcategory) {
            $this->messageService->sendText($from, 
                '❌ La subcategoría seleccionada no existe. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Guardar subcategoría en la sesión
        $session->update([
            'categoria_gasto_id' => $subcategory->id,
            'estado_actual' => 'SELECTING_AMOUNT_WITHOUT_VAT',
        ]);

        Log::info('WhatsApp Flow Handler - Subcategoría seleccionada', [
            'from' => $from,
            'session_id' => $session->id,
            'subcategory_id' => $subcategory->id,
            'subcategory_name' => $subcategory->nombre,
        ]);

        // Enviar confirmación y solicitar monto
        $confirmationMessage = "✅ Subcategoría seleccionada: {$subcategory->nombre}\n\n" .
                               "Ahora por favor, ingresa el monto del gasto (solo números):";
        $this->messageService->sendText($from, $confirmationMessage);
    }

    /**
     * Handle manual date input from user
     */
    public function handleManualDateInput(string $from, ?string $messageText, WhatsAppSession $session): void
    {
        Log::info('WhatsApp Flow Handler - Procesando fecha manual', [
            'from' => $from,
            'message_text' => $messageText,
            'session_id' => $session->id,
        ]);

        $parsedDate = $this->parseAndValidateDate($messageText);

        if (!$parsedDate) {
            $this->messageService->sendText($from, 
                '❌ Fecha no válida. Por favor, usa el formato DD/MM/AAAA\n\nEjemplo: 25/12/2023'
            );
            return;
        }

        // Guardar fecha válida
        $session->update([
            'fecha_gasto' => $parsedDate->toDateString(),
            'estado_actual' => 'SELECTING_PROPERTY',
        ]);

        Log::info('WhatsApp Flow Handler - Fecha manual guardada', [
            'from' => $from,
            'session_id' => $session->id,
            'fecha_gasto' => $parsedDate->toDateString(),
            'fecha_original' => $messageText,
        ]);

        // Enviar confirmación y listado de inmuebles
        $successMessage = "✅ Fecha registrada: {$parsedDate->format('d/m/Y')}";
        $this->messageService->sendText($from, $successMessage);
        
        // Enviar listado de inmuebles para seleccionar
        $properties = $this->dataService->getActiveProperties();
        $this->listService->sendPropertyList($from, $properties);
    }

    /**
     * Send next step message based on current session state
     */
    public function sendNextStepMessage(string $from, WhatsAppSession $session): void
    {
        switch ($session->estado_actual) {
            case 'SELECTING_DATE':
                // Reenviar mensaje de selección de fecha
                $this->sendInitialMessage($from);
                break;
                
            case 'SELECTING_PROPERTY':
                // Enviar listado de inmuebles
                $properties = $this->dataService->getActiveProperties();
                $this->listService->sendPropertyList($from, $properties);
                break;
                
            case 'SELECTING_CATEGORY':
                // Enviar listado de categorías (filtrado por tipo Egreso)
                $categories = $this->dataService->getActiveCategoriesByType('Egreso');
                $this->listService->sendCategoryList($from, $categories, 'Gastos');
                break;
                
            case 'SELECTING_SUBCATEGORY':
                // Enviar listado de subcategorías si tenemos categoría seleccionada
                if ($session->tipo_categoria_id) {
                    $subcategories = $this->dataService->getActiveSubcategoriesByCategory($session->tipo_categoria_id);
                    $this->listService->sendSubcategoryList($from, $subcategories);
                } else {
                    // Si no hay categoría, regresar a selección de categoría
                    $categories = $this->dataService->getActiveCategoriesByType('Egreso');
                    $this->listService->sendCategoryList($from, $categories, 'Gastos');
                    $session->update(['estado_actual' => 'SELECTING_CATEGORY']);
                }
                break;
                
            case 'SELECTING_AMOUNT_WITHOUT_VAT':
                // Solicitar monto del gasto sin IVA
                $this->messageService->sendText($from, 
                    '💰 Por favor, ingresa el monto del gasto sin IVA (solo números):'
                );
                break;
                
            case 'SELECTING_VAT':
                // Reenviar opciones de IVA con el monto calculado dinámicamente
                $this->sendVATOptions($from, $session->monto_sin_iva);
                break;
                
            case 'SELECTING_TOTAL_AMOUNT':
                // Reenviar solicitud de confirmación del total con botones
                $montoTotal = $session->monto_total;
                $confirmationMessage = "💰 *Monto total: $" . number_format($montoTotal, 2, ',', '.') . "*\n\n" .
                                       "¿Confirmas este monto total?";
                
                $buttons = [
                    ['id' => 'CONFIRM_TOTAL', 'title' => 'Confirmar'],
                    ['id' => 'MODIFY_TOTAL', 'title' => 'Modificar']
                ];
                
                $this->messageService->sendButtons($from, $confirmationMessage, $buttons);
                break;
                
            case 'SELECTING_TOTAL_AMOUNT_MANUAL':
                // Reenviar solicitud de monto total manual
                $this->messageService->sendText($from, 
                    'Por favor, ingresa el monto total del gasto (ej: 119000, 150000):'
                );
                break;
                
            case 'SELECTING_OBSERVATIONS':
                // Reenviar solicitud de observaciones
                $message = "Por favor, ingresa alguna observación o escribe 'NO' si no hay:";
                $this->messageService->sendText($from, $message);
                break;
                
            case 'COMPLETED':
                // El flujo ya está completado, no hacer nada
                break;
                
            case 'CANCELLED':
                // El flujo fue cancelado, reiniciar
                $this->sendInitialMessage($from);
                $session->update(['estado_actual' => 'SELECTING_DATE']);
                break;
                
            default:
                // Para cualquier otro estado, reiniciar el flujo
                Log::info('WhatsApp Flow Handler - Reiniciando flujo por estado no manejado', [
                    'from' => $from,
                    'estado_actual' => $session->estado_actual,
                ]);
                $this->sendInitialMessage($from);
                $session->update(['estado_actual' => 'SELECTING_DATE']);
                break;
        }
    }

    /**
     * Send initial message with date selection buttons
     */
    public function sendInitialMessage(string $to): void
    {
        $body = "📅 ¿Cuándo fue el gasto que quieres registrar?\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $buttons = [
            ['id' => 'TODAY', 'title' => '📆 Hoy'],
            ['id' => 'OTHER_DATE', 'title' => '📅 Otra fecha']
        ];

        $response = $this->messageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Flow Handler - Mensaje inicial enviado', [
                'to' => $to,
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Flow Handler - Error al enviar mensaje inicial', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Parse date from text input
     */
    private function parseDateFromText(string $text): ?\Carbon\Carbon
    {
        $cleanText = trim($text);
        
        // Formatos de fecha para parsear
        $formats = [
            'd/m/Y',    // 25/12/2023
            'd-m-Y',    // 25-12-2023
            'm/d/Y',    // 12/25/2023
            'm-d-Y',    // 12-25-2023
        ];

        foreach ($formats as $format) {
            try {
                $date = \Carbon\Carbon::createFromFormat($format, $cleanText);
                
                // Validar que la fecha no sea futura
                if ($date->isFuture()) {
                    Log::warning('WhatsApp Flow Handler - Fecha futura detectada', [
                        'input' => $cleanText,
                        'parsed_date' => $date->toDateString(),
                        'format' => $format
                    ]);
                    continue;
                }
                
                // Validar que no sea muy antigua (más de 1 año)
                if ($date->diffInDays(now()) > 365) {
                    Log::warning('WhatsApp Flow Handler - Fecha muy antigua detectada', [
                        'input' => $cleanText,
                        'parsed_date' => $date->toDateString(),
                        'format' => $format,
                        'days_diff' => $date->diffInDays(now())
                    ]);
                    continue;
                }
                
                return $date;
            } catch (\Exception $e) {
                // Continuar con el siguiente formato
                continue;
            }
        }

        Log::warning('WhatsApp Flow Handler - No se pudo parsear la fecha', [
            'input' => $cleanText,
            'tried_formats' => $formats
        ]);

        return null;
    }

    /**
     * Handle amount input from user
     */
    public function handleAmountInput(string $from, string $message, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_AMOUNT_WITHOUT_VAT') {
            Log::warning('WhatsApp Flow Handler - Monto recibido en estado incorrecto', [
                'from' => $from,
                'message' => $message,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar que el monto sea un número válido
        $amount = $this->parseAmount($message);
        if ($amount === null || $amount <= 0) {
            $this->messageService->sendText($from, 
                '❌ Monto inválido. Por favor, ingresa solo números positivos (ej: 15000, 250.50)'
            );
            return;
        }

        // Guardar monto en la sesión
        Log::info('WhatsApp Flow Handler - Actualizando estado a SELECTING_VAT', [
            'from' => $from,
            'session_id' => $session->id,
            'monto_sin_iva' => $amount,
            'estado_anterior' => $session->estado_actual,
            'estado_nuevo' => 'SELECTING_VAT'
        ]);
        
        $session->update([
            'monto_sin_iva' => $amount,
            'estado_actual' => 'SELECTING_VAT',
        ]);

        Log::info('WhatsApp Flow Handler - Estado actualizado', [
            'from' => $from,
            'session_id' => $session->id,
            'estado_actual' => $session->fresh()->estado_actual
        ]);

        Log::info('WhatsApp Flow Handler - Monto ingresado', [
            'from' => $from,
            'session_id' => $session->id,
            'amount' => $amount,
        ]);

        // Enviar confirmación y solicitar IVA
        $confirmationMessage = "✅ Monto sin IVA registrado: $" . number_format($amount, 2, ',', '.') . "\n\n" .
                               "Ahora selecciona el monto de IVA:";
        $this->sendVATOptions($from, $amount);
    }

    /**
     * Send VAT options
     */
    private function sendVATOptions(string $to, float $montoSinIva): void
    {
        // Calcular 19% del monto sin IVA
        $ivaCalculado = $montoSinIva * 0.19;
        
        $buttons = [
            ['id' => 'VAT_CALCULADO', 'title' => 'IVA $' . number_format($ivaCalculado, 0, ',', '.')],
            ['id' => 'VAT_0', 'title' => 'IVA $0'],
            ['id' => 'VAT_OTRO', 'title' => 'Otro valor']
        ];

        $body = "💰 Selecciona el monto del IVA:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->messageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Flow Handler - Opciones de IVA enviadas', [
                'to' => $to,
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Flow Handler - Error al enviar opciones de IVA', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Handle VAT selection
     */
    public function handleVATSelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_VAT') {
            Log::warning('WhatsApp Flow Handler - IVA recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar y mapear el IVA (ahora son montos directos)
        $vatAmounts = [
            'VAT_CALCULADO' => 'CALCULAR', // Se calculará dinámicamente
            'VAT_0' => 0,
            'VAT_OTRO' => null // Pedirá valor manual
        ];

        if (!isset($vatAmounts[$buttonId])) {
            $this->messageService->sendText($from, 
                '❌ Opción de IVA inválida. Por favor, selecciona una opción válida.'
            );
            return;
        }

        // Si es VAT_CALCULADO, calcular el 19% del monto sin IVA
        if ($buttonId === 'VAT_CALCULADO') {
            $ivaAmount = $session->monto_sin_iva * 0.19;
        } else {
            $ivaAmount = $vatAmounts[$buttonId];
        }

        if ($ivaAmount === null) {
            // Es VAT_OTRO, pedir valor manual inmediatamente
            $this->messageService->sendText($from, 
                'Por favor, ingresa el monto del IVA (ej: 19000, 0):'
            );
            return;
        }

        // Calcular monto total y guardar
        $montoSinIva = $session->monto_sin_iva;
        $montoTotal = $montoSinIva + $ivaAmount;

        $session->update([
            'iva' => $ivaAmount,
            'monto_total' => $montoTotal,
            'estado_actual' => 'SELECTING_TOTAL_AMOUNT',
        ]);

        Log::info('WhatsApp Flow Handler - IVA seleccionado', [
            'from' => $from,
            'session_id' => $session->id,
            'iva_amount' => $ivaAmount,
            'total_amount' => $montoTotal,
        ]);

        // Enviar confirmación y solicitar confirmación del total con botones
        $confirmationMessage = "✅ IVA: $" . number_format($ivaAmount, 2, ',', '.') . "\n\n" .
                               "💰 *Monto total: $" . number_format($montoTotal, 2, ',', '.') . "*\n\n" .
                               "¿Confirmas este monto total?";
        
        $buttons = [
            ['id' => 'CONFIRM_TOTAL', 'title' => 'Confirmar'],
            ['id' => 'MODIFY_TOTAL', 'title' => 'Modificar']
        ];
        
        $this->messageService->sendButtons($from, $confirmationMessage, $buttons);
    }

    /**
     * Handle total amount confirmation
     */
    public function handleTotalAmountConfirmation(string $from, string $message, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_TOTAL_AMOUNT') {
            Log::warning('WhatsApp Flow Handler - Confirmación de total recibida en estado incorrecto', [
                'from' => $from,
                'message' => $message,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Procesar confirmación
        $confirmation = strtoupper(trim($message));
        
        if ($confirmation === 'SI' || $confirmation === 'S') {
            // Confirmado, pasar a monto sin IVA
            $session->update(['estado_actual' => 'SELECTING_AMOUNT_WITHOUT_VAT']);
            
            $this->messageService->sendText($from, 
                '✅ Fecha confirmada\n\n' .
                'Por favor, ingresa el monto sin IVA (ej: 15000, 250.50):'
            );
        } else {
            $this->messageService->sendText($from, 
                '❌ Respuesta inválida. Por favor, responde SI o NO:'
            );
        }
    }

    /**
     * Handle observations input
     */
    public function handleObservationsInput(string $from, string $message, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_OBSERVATIONS') {
            Log::warning('WhatsApp Flow Handler - Observaciones recibidas en estado incorrecto', [
                'from' => $from,
                'message' => $message,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Procesar observaciones
        $observations = trim($message);
        if (strtoupper($observations) === 'NO' || strtoupper($observations) === 'N/A') {
            $observations = null;
        }

        // Guardar observaciones y finalizar el proceso
        $session->update([
            'observaciones' => $observations,
            'estado_actual' => 'COMPLETED',
        ]);

        Log::info('WhatsApp Flow Handler - Observaciones ingresadas', [
            'from' => $from,
            'session_id' => $session->id,
            'observations' => $observations,
        ]);

        // Guardar el gasto completo
        $this->saveExpense($from, $session);
    }

    /**
     * Save the complete expense
     */
    private function saveExpense(string $from, WhatsAppSession $session): void
    {
        try {
            // Validar que todos los datos necesarios estén presentes
            if (!$session->fecha_gasto || !$session->inmueble_id || !$session->tipo_categoria_id || 
                !$session->categoria_gasto_id || !$session->monto_sin_iva || !$session->monto_total) {
                
                $this->messageService->sendText($from, 
                    '❌ Error: Faltan datos necesarios para guardar el gasto. Por favor, inicia el proceso nuevamente.'
                );
                
                // Resetear la sesión
                $session->update(['estado_actual' => 'CANCELLED']);
                return;
            }

            // Crear el gasto (aquí iría la lógica para guardar en la base de datos)
            $expenseData = [
                'fecha_gasto' => $session->fecha_gasto,
                'inmueble_id' => $session->inmueble_id,
                'tipo_categoria_id' => $session->tipo_categoria_id,
                'categoria_gasto_id' => $session->categoria_gasto_id,
                'monto_sin_iva' => $session->monto_sin_iva,
                'iva' => $session->iva,
                'monto_total' => $session->monto_total,
                'observaciones' => $session->observaciones,
                'telefono' => $from,
                'created_at' => now()
            ];

            // TODO: Implementar el guardado real en la base de datos
            // $gasto = Gasto::create($expenseData);

            Log::info('WhatsApp Flow Handler - Gasto guardado', [
                'from' => $from,
                'session_id' => $session->id,
                'expense_data' => $expenseData,
            ]);

            // Enviar confirmación final
            $confirmationMessage = "✅ *Gasto registrado exitosamente*\n\n" .
                                   "📅 Fecha: {$session->fecha_gasto}\n" .
                                   "💰 Monto sin IVA: $" . number_format($session->monto_sin_iva, 2, ',', '.') . "\n" .
                                   "� IVA: $" . number_format($session->iva, 2, ',', '.') . "\n" .
                                   "💰 Monto total: $" . number_format($session->monto_total, 2, ',', '.') . "\n" .
                                   "📝 Observaciones: " . ($session->observaciones ?: 'Ninguna') . "\n\n" .
                                   "¡Gracias por usar el sistema! 🎉";
            
            $this->messageService->sendText($from, $confirmationMessage);

        } catch (\Exception $e) {
            Log::error('WhatsApp Flow Handler - Error al guardar gasto', [
                'from' => $from,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $this->messageService->sendText($from, 
                '❌ Error al guardar el gasto. Por favor, intenta nuevamente o contacta al administrador.'
            );
        }
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
