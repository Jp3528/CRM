<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Observers\AutomationObserver;
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

        // Motor de automatizaciones (Fase 11): hook central de eventos.
        // Sin duplicar en controllers/servicios; la ejecución se difiere a
        // after-commit desde el dispatcher (el evento confirma primero).
        foreach ([Lead::class, Contact::class, Opportunity::class, Task::class, Ticket::class, Quote::class, Sale::class, Invoice::class, Campaign::class] as $model) {
            $model::observe(AutomationObserver::class);
        }
    }
}
