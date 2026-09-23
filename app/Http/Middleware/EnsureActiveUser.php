<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impide el acceso a usuarios inactivos en cada solicitud autenticada.
 *
 * Si un usuario activo pasa a inactivo, la siguiente petición con sesión
 * válida cierra la sesión y redirige al login con un mensaje claro.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cuenta desactivada.'], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
        }

        return $next($request);
    }
}
