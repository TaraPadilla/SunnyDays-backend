<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class WhatsAppListService
{
    protected $whatsAppMessageService;

    public function __construct(WhatsAppMessageService $whatsAppMessageService)
    {
        $this->whatsAppMessageService = $whatsAppMessageService;
    }

    /**
     * Enviar listado de inmuebles como botones o lista
     */
    public function sendPropertyList(string $to, array $properties): void
    {
        if (empty($properties)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay inmuebles disponibles en este momento. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay más de 3 inmuebles, enviar como lista (límite de WhatsApp)
        if (count($properties) > 3) {
            $this->sendPropertyAsList($to, $properties);
            return;
        }

        // Enviar como botones si son 3 o menos
        $buttons = array_slice($properties, 0, 3); // Máximo 3 botones por mensaje (límite WhatsApp)
        
        $body = "🏢 Selecciona el inmueble donde registraste el gasto:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp List Service - Listado de inmuebles enviado', [
                'to' => $to,
                'properties_count' => count($properties),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar listado de inmuebles', [
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
            Log::info('WhatsApp List Service - Lista de inmuebles enviada', [
                'to' => $to,
                'properties_count' => count($properties),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar lista de inmuebles', [
                'to' => $to,
            ]);
        }
    }

    /**
     * Enviar listado de categorías como botones o lista
     */
    public function sendCategoryList(string $to, array $categories, string $tipo): void
    {
        if (empty($categories)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay categorías de tipo ' . $tipo . ' disponibles. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay más de 3 categorías, enviar como lista (límite de WhatsApp)
        if (count($categories) > 3) {
            $this->sendCategoryAsList($to, $categories, $tipo);
            return;
        }

        // Enviar como botones si son 3 o menos
        $buttons = array_slice($categories, 0, 3); // Máximo 3 botones por mensaje (límite WhatsApp)
        
        $body = "📋 Selecciona la categoría del gasto:\n\n" .
                "*Tipo: " . ucfirst($tipo) . "*\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp List Service - Listado de categorías enviado', [
                'to' => $to,
                'tipo' => $tipo,
                'categories_count' => count($categories),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar listado de categorías', [
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
            Log::info('WhatsApp List Service - Lista de categorías enviada', [
                'to' => $to,
                'tipo' => $tipo,
                'categories_count' => count($categories),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar lista de categorías', [
                'to' => $to,
                'tipo' => $tipo,
            ]);
        }
    }

    /**
     * Enviar listado de subcategorías como botones o lista
     */
    public function sendSubcategoryList(string $to, array $subcategories): void
    {
        if (empty($subcategories)) {
            $this->whatsAppMessageService->sendText($to, 
                '❌ No hay subcategorías disponibles para esta categoría. Por favor, contacta al administrador.'
            );
            return;
        }

        // Si hay más de 3 subcategorías, enviar como lista (límite de WhatsApp)
        if (count($subcategories) > 3) {
            $this->sendSubcategoryAsList($to, $subcategories);
            return;
        }

        // Enviar como botones si son 3 o menos
        $buttons = array_slice($subcategories, 0, 3); // Máximo 3 botones por mensaje (límite WhatsApp)
        
        $body = "📝 Selecciona la subcategoría del gasto:\n\n" .
                "*Puedes escribir CANCELAR en cualquier momento para cancelar el proceso*";

        $response = $this->whatsAppMessageService->sendButtons($to, $body, $buttons);

        if ($response) {
            Log::info('WhatsApp List Service - Listado de subcategorías enviado', [
                'to' => $to,
                'subcategories_count' => count($subcategories),
                'buttons_sent' => count($buttons),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar listado de subcategorías', [
                'to' => $to,
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
                'title' => count($chunks) > 1 ? "Subcats " . ($index + 1) : "Subcategorías",
                'rows' => $rows
            ];
        }

        $body = "📝 Selecciona la subcategoría del gasto";
        $buttonText = "Ver subcategorías";

        $response = $this->whatsAppMessageService->sendList($to, $body, $buttonText, $sections);

        if ($response) {
            Log::info('WhatsApp List Service - Lista de subcategorías enviada', [
                'to' => $to,
                'subcategories_count' => count($subcategories),
                'sections_count' => count($sections),
                'response_status' => $response['status'],
            ]);
        } else {
            Log::error('WhatsApp List Service - Error al enviar lista de subcategorías', [
                'to' => $to,
            ]);
        }
    }
}
