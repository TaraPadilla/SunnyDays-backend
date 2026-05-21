<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDemoUserWrites
{
    private const DEMO_EMAIL = 'testadmin@test.com';

    /**
     * Endpoints that use write HTTP verbs but do not change business data.
     *
     * @var array<int, string>
     */
    private const ALLOWED_DEMO_PATHS = [
        'api/logout',
        'api/generar-balance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->email === self::DEMO_EMAIL
            && !$request->isMethodSafe()
            && !$this->isAllowedDemoPath($request)
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'El usuario de prueba no puede realizar cambios en la informacion.',
            ], 403);
        }

        return $next($request);
    }

    private function isAllowedDemoPath(Request $request): bool
    {
        foreach (self::ALLOWED_DEMO_PATHS as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
