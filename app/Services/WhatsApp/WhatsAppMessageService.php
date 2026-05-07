<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageService
{
    /**
     * Send a text message via WhatsApp Cloud API
     */
    public function sendText(string $to, string $message): ?array
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (!$phoneNumberId || !$accessToken) {
            Log::warning('WhatsApp Message Service - Configuración incompleta', [
                'phone_number_id_configured' => !empty($phoneNumberId),
                'access_token_configured' => !empty($accessToken),
            ]);
            return null;
        }

        $outgoingPayload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];

        $endpoint = "https://graph.facebook.com/v25.0/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($accessToken)
                ->post($endpoint, $outgoingPayload);

            Log::info('WhatsApp Message Service - Mensaje enviado', [
                'to' => $to,
                'message_type' => 'text',
                'message_body' => $message,
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return [
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Message Service - Error al enviar mensaje', [
                'to' => $to,
                'message_type' => 'text',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send a message with buttons via WhatsApp Cloud API
     */
    public function sendButtons(string $to, string $body, array $buttons): ?array
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (!$phoneNumberId || !$accessToken) {
            Log::warning('WhatsApp Message Service - Configuración incompleta', [
                'phone_number_id_configured' => !empty($phoneNumberId),
                'access_token_configured' => !empty($accessToken),
            ]);
            return null;
        }

        // Limitar a máximo 3 botones
        $validButtons = array_slice($buttons, 0, 3);
        $buttonRows = [];
        
        foreach ($validButtons as $button) {
            $buttonRows[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? '',
                    'title' => $button['title'] ?? ''
                ]
            ];
        }

        $outgoingPayload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $body
                ],
                'action' => [
                    'buttons' => $buttonRows
                ]
            ]
        ];

        $endpoint = "https://graph.facebook.com/v25.0/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($accessToken)
                ->post($endpoint, $outgoingPayload);

            Log::info('WhatsApp Message Service - Mensaje con botones enviado', [
                'to' => $to,
                'message_type' => 'buttons',
                'button_count' => count($validButtons),
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return [
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Message Service - Error al enviar mensaje con botones', [
                'to' => $to,
                'message_type' => 'buttons',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send a list message via WhatsApp Cloud API
     */
    public function sendList(string $to, string $body, string $buttonText, array $sections): ?array
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (!$phoneNumberId || !$accessToken) {
            Log::warning('WhatsApp Message Service - Configuración incompleta', [
                'phone_number_id_configured' => !empty($phoneNumberId),
                'access_token_configured' => !empty($accessToken),
            ]);
            return null;
        }

        $validSections = array_slice($sections, 0, 10); // Límite de WhatsApp
        
        $sectionRows = [];
        foreach ($validSections as $section) {
            $rows = [];
            if (isset($section['rows']) && is_array($section['rows'])) {
                $rows = array_slice($section['rows'], 0, 10); // Límite por sección
            }

            $sectionRows[] = [
                'title' => $section['title'] ?? '',
                'rows' => $rows
            ];
        }

        $outgoingPayload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $body
                ],
                'body' => [
                    'text' => 'Selecciona una opción'
                ],
                'footer' => [
                    'text' => ''
                ],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sectionRows
                ]
            ]
        ];

        $endpoint = "https://graph.facebook.com/v25.0/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($accessToken)
                ->post($endpoint, $outgoingPayload);

            Log::info('WhatsApp Message Service - Mensaje lista enviado', [
                'to' => $to,
                'message_type' => 'list',
                'section_count' => count($validSections),
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return [
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp Message Service - Error al enviar mensaje lista', [
                'to' => $to,
                'message_type' => 'list',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
