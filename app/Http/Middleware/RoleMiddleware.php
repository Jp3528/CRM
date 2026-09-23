<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('role:Administrador,Supervisor')
 * Acepta comas o pipes como separador. Superadministrador siempre pasa
 * (vía hasAnyRole + excepción centralizada en el modelo).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            return redirect()->route('login');
        }

        $required = collect($roles)
            ->flatMap(fn (string $r) => preg_split('/[|,]/', $r) ?: [])
            ->map(fn (string $r) => trim($r))
            ->filter()
            ->all();

        if ($user->isSuperAdmin() || $user->hasAnyRole($required)) {
            return $next($request);
        }

        abort(403);
    }
}
