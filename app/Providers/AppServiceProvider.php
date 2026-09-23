<?php

namespace App\Providers;

use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RBAC operativo: cualquier habilidad cuyo nombre coincida con un
        // permiso (p. ej. "users.view") se autoriza vía User::hasPermission().
        // Superadministrador pasa siempre (excepción centralizada en el modelo).
        // Rol Consulta puro (sin otros roles): solo lectura aunque existan
        // permisos de escritura asignados por error.
        Gate::before(function (?User $user, string $ability): ?bool {
            if (! $user) {
                return null;
            }

            if (DataScope::isReadOnly($user) && in_array($ability, DataScope::WRITE_ABILITIES, true)) {
                return false;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
