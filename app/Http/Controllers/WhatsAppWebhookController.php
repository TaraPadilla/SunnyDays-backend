<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación del webhook de WhatsApp Cloud API
     * Meta requiere este endpoint para verificar la URL del webhook
     */
    public function verify(Request $request): Response
    {
        Log::info('WhatsApp Webhook - Verificación iniciada', [
            'all_query_params' => $request->query(),
            'hub_mode' => $request->query('hub_mode'),
            'hub_verify_token' => $request->query('hub_verify_token'),
            'hub_challenge' => $request->query('hub_challenge'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $hubMode = $request->query('hub_mode');
        $hubVerifyToken = $request->query('hub_verify_token');
        $hubChallenge = $request->query('hub_challenge');
        $expectedToken = env('WHATSAPP_VERIFY_TOKEN');

        // Validar que el modo sea 'subscribe'
        if ($hubMode !== 'subscribe') {
            Log::warning('WhatsApp Webhook - Modo inválido', [
                'expected' => 'subscribe',
                'received' => $hubMode,
            ]);
            return response('Modo inválido', 403);
        }

        // Validar que el token coincida
        if ($hubVerifyToken !== $expectedToken) {
            Log::warning('WhatsApp Webhook - Token de verificación inválido', [
                'expected' => $expectedToken ? '[CONFIGURADO]' : '[NO CONFIGURADO]',
                'received' => $hubVerifyToken ? '[ENVIADO]' : '[NO ENVIADO]',
            ]);
            return response('Token de verificación inválido', 403);
        }

        // Validar que exista el challenge
        if (!$hubChallenge) {
            Log::warning('WhatsApp Webhook - Challenge no proporcionado');
            return response('Challenge no proporcionado', 400);
        }

        Log::info('WhatsApp Webhook - Verificación exitosa', [
            'challenge_length' => strlen($hubChallenge),
        ]);

        // Responder con el challenge en texto plano y status 200
        return response($hubChallenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Recepción de eventos y mensajes del webhook de WhatsApp Cloud API
     * Meta envía aquí todos los eventos: mensajes, cambios de estado, etc.
     */
    public function receive(Request $request): \Illuminate\Http\JsonResponse
    {
        // Registrar información completa para debugging
        Log::info('WhatsApp Webhook - Evento recibido', [
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
            'json' => $request->json(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
        ]);

        // Responder siempre con éxito para confirmar recepción
        return response()->json(['success' => true], 200);
    }
}
