<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('permission:users.view')
 * Acepta comas o pipes como separador (basta con uno de ellos).
 * Superadministrador siempre pasa vía User::hasPermission().
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            return redirect()->route('login');
        }

        $required = collect($permissions)
            ->flatMap(fn (string $p) => preg_split('/[|,]/', $p) ?: [])
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->all();

        if ($user->hasAnyPermission($required)) {
            return $next($request);
        }

        abort(403);
    }
}
