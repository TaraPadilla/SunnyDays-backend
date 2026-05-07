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
        
        // Enviar listado de categorías (tipo 'Egreso' para gastos)
        $categories = $this->dataService->getActiveCategoriesByType('Egreso');
        $this->listService->sendCategoryList($from, $categories, 'Egreso');
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
                // Enviar listado de categorías (tipo 'Egreso' para gastos)
                $categories = $this->dataService->getActiveCategoriesByType('Egreso');
                $this->listService->sendCategoryList($from, $categories, 'Egreso');
                break;
                
            case 'SELECTING_SUBCATEGORY':
                // Enviar listado de subcategorías si tenemos categoría seleccionada
                if ($session->tipo_categoria_id) {
                    $subcategories = $this->dataService->getActiveSubcategoriesByCategory($session->tipo_categoria_id);
                    $this->listService->sendSubcategoryList($from, $subcategories);
                } else {
                    // Si no hay categoría, regresar a selección de categoría
                    $categories = $this->dataService->getActiveCategoriesByType('Egreso');
                    $this->listService->sendCategoryList($from, $categories, 'Egreso');
                    $session->update(['estado_actual' => 'SELECTING_CATEGORY']);
                }
                break;
                
            case 'SELECTING_AMOUNT':
                // Solicitar monto del gasto
                $this->messageService->sendText($from, 
                    '💰 Por favor, ingresa el monto del gasto (solo números):'
                );
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
     * Parse and validate date in various formats
     */
    private function parseAndValidateDate(?string $dateText): ?\Carbon\Carbon
    {
        if (!$dateText) {
            return null;
        }

        // Limpiar el texto: quitar espacios extra
        $cleanText = trim($dateText);

        // Intentar diferentes formatos
        $formats = [
            'd/m/Y',    // 25/12/2023
            'd-m-Y',    // 25-12-2023
            'd/m/y',    // 25/12/23
            'd-m-y',    // 25-12-23
            'Y-m-d',    // 2023-12-25
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
}
