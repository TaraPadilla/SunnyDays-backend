<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Login
     */
    public function login(Request $request)
    {
        Log::info('[AuthController] login: petición recibida', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('[AuthController] login: credenciales incorrectas', [
                'email' => $request->email,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        
        Log::info('[AuthController] login: éxito', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Log::info('[AuthController] logout: petición recibida', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip()
        ]);

        $request->user()->currentAccessToken()->delete();
        
        Log::info('[AuthController] logout: éxito', [
            'user_id' => $request->user()->id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Sesión cerrada'
        ]);
    }

    /**
     * Usuario autenticado
     */
    public function me(Request $request)
    {
        Log::info('[AuthController] me: petición recibida', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip()
        ]);

        return response()->json($request->user());
    }
}