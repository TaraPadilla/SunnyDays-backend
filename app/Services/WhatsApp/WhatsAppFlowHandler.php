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
            // Pedir fecha específica
            $session->update(['estado_actual' => 'SELECTING_DATE_MANUAL']);

            Log::info('WhatsApp Flow Handler - Solicitando fecha manual', [
                'from' => $from,
                'session_id' => $session->id,
            ]);

            $message = "Por favor, escribe la fecha del gasto en formato DD/MM/AAAA\n\nEjemplo: 25/12/2023";
            $this->messageService->sendText($from, $message);
            
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
            'estado_actual' => 'SELECTING_AMOUNT',
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
                
            case 'SELECTING_DATE_MANUAL':
                // Reenviar solicitud de fecha manual
                $message = "Por favor, escribe la fecha del gasto en formato DD/MM/AAAA\n\nEjemplo: 25/12/2023";
                $this->messageService->sendText($from, $message);
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
                
            case 'SELECTING_AMOUNT':
                // Solicitar monto del gasto
                $this->messageService->sendText($from, 
                    '💰 Por favor, ingresa el monto del gasto (solo números):'
                );
                break;
                
            case 'SELECTING_PAYMENT_TYPE':
                // Reenviar opciones de tipo de pago
                $this->sendPaymentTypeOptions($from);
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
        if ($session->estado_actual !== 'SELECTING_AMOUNT') {
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
        $session->update([
            'monto' => $amount,
            'estado_actual' => 'SELECTING_PAYMENT_TYPE',
        ]);

        Log::info('WhatsApp Flow Handler - Monto ingresado', [
            'from' => $from,
            'session_id' => $session->id,
            'amount' => $amount,
        ]);

        // Enviar confirmación y solicitar tipo de pago
        $confirmationMessage = "✅ Monto registrado: $" . number_format($amount, 2, ',', '.') . "\n\n" .
                               "Ahora selecciona el tipo de pago:";
        $this->sendPaymentTypeOptions($from);
    }

    /**
     * Send payment type options
     */
    private function sendPaymentTypeOptions(string $to): void
    {
        $buttons = [
            ['id' => 'PAYMENT_EFECTIVO', 'title' => '💵 Efectivo'],
            ['id' => 'PAYMENT_TRANSFERENCIA', 'title' => '🏦 Transferencia'],
            ['id' => 'PAYMENT_TARJETA', 'title' => '💳 Tarjeta'],
            ['id' => 'PAYMENT_OTRO', 'title' => '📋 Otro']
        ];

        $body = "💳 Selecciona el tipo de pago:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->messageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp Flow Handler - Opciones de pago enviadas', [
                'to' => $to,
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp Flow Handler - Error al enviar opciones de pago', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Handle payment type selection
     */
    public function handlePaymentTypeSelection(string $from, string $buttonId, WhatsAppSession $session): void
    {
        if ($session->estado_actual !== 'SELECTING_PAYMENT_TYPE') {
            Log::warning('WhatsApp Flow Handler - Tipo de pago recibido en estado incorrecto', [
                'from' => $from,
                'button_id' => $buttonId,
                'estado_actual' => $session->estado_actual,
            ]);
            return;
        }

        // Validar y mapear el tipo de pago
        $paymentTypes = [
            'PAYMENT_EFECTIVO' => 'Efectivo',
            'PAYMENT_TRANSFERENCIA' => 'Transferencia',
            'PAYMENT_TARJETA' => 'Tarjeta',
            'PAYMENT_OTRO' => 'Otro'
        ];

        if (!isset($paymentTypes[$buttonId])) {
            $this->messageService->sendText($from, 
                '❌ Opción de pago inválida. Por favor, selecciona una opción válida.'
            );
            return;
        }

        $paymentType = $paymentTypes[$buttonId];

        // Guardar tipo de pago en la sesión
        $session->update([
            'tipo_pago' => $paymentType,
            'estado_actual' => 'SELECTING_OBSERVATIONS',
        ]);

        Log::info('WhatsApp Flow Handler - Tipo de pago seleccionado', [
            'from' => $from,
            'session_id' => $session->id,
            'payment_type' => $paymentType,
        ]);

        // Enviar confirmación y solicitar observaciones
        $confirmationMessage = "✅ Tipo de pago: {$paymentType}\n\n" .
                               "Por favor, ingresa alguna observación o escribe 'NO' si no hay:";
        $this->messageService->sendText($from, $confirmationMessage);
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
            if (!$session->fecha || !$session->inmueble_id || !$session->tipo_categoria_id || 
                !$session->categoria_gasto_id || !$session->monto || !$session->tipo_pago) {
                
                $this->messageService->sendText($from, 
                    '❌ Error: Faltan datos necesarios para guardar el gasto. Por favor, inicia el proceso nuevamente.'
                );
                
                // Resetear la sesión
                $session->update(['estado_actual' => 'CANCELLED']);
                return;
            }

            // Crear el gasto (aquí iría la lógica para guardar en la base de datos)
            $expenseData = [
                'fecha' => $session->fecha,
                'inmueble_id' => $session->inmueble_id,
                'tipo_categoria_id' => $session->tipo_categoria_id,
                'categoria_gasto_id' => $session->categoria_gasto_id,
                'monto' => $session->monto,
                'tipo_pago' => $session->tipo_pago,
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
                                   "📅 Fecha: {$session->fecha}\n" .
                                   "💰 Monto: $" . number_format($session->monto, 2, ',', '.') . "\n" .
                                   "💳 Tipo de pago: {$session->tipo_pago}\n" .
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
