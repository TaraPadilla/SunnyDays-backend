<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    public function process(Request $request)
    {
        try {
            // 1. Validación
            $request->validate([
                'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            $file = $request->file('document');

            // 2. Llamada a n8n
            $response = Http::attach(
                'data',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName()
            )->post('https://TU-URL-DE-N8N-AQUI/webhook/TU-ID');

            // 3. Verificamos si n8n respondió bien
            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'data' => $response->json() // Aquí recibes el JSON de n8n
                ], 200);
            }

            return response()->json(['status' => 'error', 'message' => 'Error en n8n'], 502);

        } catch (\Exception $e) {
            Log::error('Error procesando documento: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}