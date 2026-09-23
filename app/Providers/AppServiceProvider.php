<?php

namespace App\Providers;

use App\Models\User;
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
        Gate::before(function (?User $user, string $ability): ?bool {
            if (! $user) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
