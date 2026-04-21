<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Si la autenticación está desactivada en la configuración, continuar sin autenticar
        if (Config::get('app.disable_auth', false)) {
            return $next($request);
        }

        // Si la autenticación está activada, aplicar el middleware de autenticación
        return app('auth')->guard('sanctum')->check() 
            ? $next($request) 
            : response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
