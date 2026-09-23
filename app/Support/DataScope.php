<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Segunda capa de autorización: alcance de datos (data scope).
 *
 * NO reemplaza el RBAC por permisos; se suma: permiso funcional + alcance.
 * Sin multi-tenancy ni jerarquías inventadas: Gerente/Supervisor trabajan
 * por mismo equipo (team_id), sin árbol gerente/subordinados.
 *
 * Niveles:
 *  - global : Superadministrador, Administrador (todo lo permitido).
 *  - team   : Gerente comercial, Supervisor (propio + mismo equipo no nulo).
 *  - own    : Vendedor, Soporte, Consulta (propio/asignado; tickets con regla propia).
 *  - own    : también es el fallback seguro para usuarios sin rol de alcance.
 *
 * Protección null-team: dos usuarios con team_id null NUNCA se consideran
 * del mismo equipo (el equipo null no agrupa).
 */
final class DataScope
{
    public const GLOBAL_ROLES = ['Superadministrador', 'Administrador'];

    public const TEAM_ROLES = ['Gerente comercial', 'Supervisor'];

    /** Habilidades de escritura bloqueadas para el rol Consulta puro. */
    public const WRITE_ABILITIES = ['create', 'update', 'delete', 'move', 'convert'];

    /**
     * Memoización por OBJETO (WeakMap), no por ID: los IDs se reutilizan
     * entre tests con RefreshDatabase y en jobs de cola; el objeto no.
     *
     * @var \WeakMap<User, string>|null
     */
    protected static ?\WeakMap $levelCache = null;

    /** @var \WeakMap<User, array<int>|null>|null null = sin restricción. */
    protected static ?\WeakMap $ownerIdsCache = null;

    public static function clearCache(): void
    {
        self::$levelCache = null;
        self::$ownerIdsCache = null;
    }

    protected static function levels(): \WeakMap
    {
        return self::$levelCache ??= new \WeakMap();
    }

    protected static function ownerIdsMap(): \WeakMap
    {
        return self::$ownerIdsCache ??= new \WeakMap();
    }

    public static function level(User $user): string
    {
        if (! isset(self::levels()[$user])) {
            self::levels()[$user] = self::resolveLevel($user);
        }

        return self::levels()[$user];
    }

    protected static function resolveLevel(User $user): string
    {
        if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
            return 'global';
        }

        if ($user->hasAnyRole(self::TEAM_ROLES)) {
            return 'team';
        }

        if ($user->hasAnyRole(['Vendedor', 'Soporte', 'Consulta'])) {
            return 'own';
        }

        return 'own';
    }

    public static function isUnconstrained(User $user): bool
    {
        return self::level($user) === 'global';
    }

    /**
     * Solo lectura: rol Consulta SIN ningún otro rol de alcance. Aunque por
     * error existan permisos de escritura asignados, el Gate los deniega.
     */
    public static function isReadOnly(User $user): bool
    {
        return $user->hasRole('Consulta')
            && ! $user->hasAnyRole([...self::GLOBAL_ROLES, ...self::TEAM_ROLES, 'Vendedor', 'Soporte']);
    }

    /**
     * IDs de usuarios cuyos registros son visibles. null = sin restricción.
     * Equipo null no agrupa: solo el propio ID.
     *
     * @return array<int>|null
     */
    public static function ownerIds(User $user): ?array
    {
        if (self::isUnconstrained($user)) {
            return null;
        }

        if (! isset(self::ownerIdsMap()[$user])) {
            $ids = [(int) $user->id];

            if (self::level($user) === 'team' && $user->team_id !== null) {
                $mates = User::where('team_id', $user->team_id)->pluck('id')->all();
                $ids = array_values(array_unique(array_merge($ids, array_map('intval', $mates))));
            }

            self::ownerIdsMap()[$user] = $ids;
        }

        return self::ownerIdsMap()[$user];
    }

    // ---------------- Propietario directo (owner_id) ----------------

    public static function scopeOwned(Builder $query, User $user, string $column = 'owner_id'): Builder
    {
        $ids = self::ownerIds($user);

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->whereIn("{$table}.{$column}", $ids);
    }

    /**
     * Registros sin responsable solo son visibles con alcance global.
     */
    public static function canAccessOwner(User $viewer, ?User $owner): bool
    {
        if (self::isUnconstrained($viewer)) {
            return true;
        }

        if (! $owner) {
            return false;
        }

        return in_array((int) $owner->id, self::ownerIds($viewer) ?? [], true);
    }

    // ---------------- Tasks (assigned_to / created_by) ----------------

    public static function scopeTasks(Builder $query, User $user): Builder
    {
        $ids = self::ownerIds($user);

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function ($q) use ($ids, $table) {
            $q->whereIn("{$table}.assigned_to", $ids)
                ->orWhereIn("{$table}.created_by", $ids);
        });
    }

    public static function canAccessTask(User $viewer, Task $task): bool
    {
        if (self::isUnconstrained($viewer)) {
            return true;
        }

        $ids = self::ownerIds($viewer) ?? [];

        return in_array((int) $task->assigned_to, $ids, true)
            || in_array((int) $task->created_by, $ids, true);
    }

    // ---------------- Tickets (regla de soporte) ----------------
    //
    // Soporte puro: asignados a él + sin asignar (cola para tomar casos).
    // NUNCA tickets asignados a otro agente.
    // Team: asignados/creados por el equipo + sin asignar (cola del equipo).
    // Own (vendedor/consulta): asignados o creados por él.

    public static function isSupportScoped(User $user): bool
    {
        return $user->hasRole('Soporte')
            && ! $user->hasAnyRole([...self::GLOBAL_ROLES, ...self::TEAM_ROLES, 'Vendedor']);
    }

    public static function scopeTickets(Builder $query, User $user): Builder
    {
        $ids = self::ownerIds($user);

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        if (self::isSupportScoped($user)) {
            return $query->where(function ($q) use ($user, $table) {
                $q->where("{$table}.assigned_to", (int) $user->id)
                    ->orWhereNull("{$table}.assigned_to");
            });
        }

        if (self::level($user) === 'team') {
            return $query->where(function ($q) use ($ids, $table) {
                $q->whereIn("{$table}.assigned_to", $ids)
                    ->orWhereIn("{$table}.created_by", $ids)
                    ->orWhereNull("{$table}.assigned_to");
            });
        }

        return $query->where(function ($q) use ($ids, $table) {
            $q->whereIn("{$table}.assigned_to", $ids)
                ->orWhereIn("{$table}.created_by", $ids);
        });
    }

    public static function canAccessTicket(User $viewer, Ticket $ticket): bool
    {
        if (self::isUnconstrained($viewer)) {
            return true;
        }

        if (self::isSupportScoped($viewer)) {
            return $ticket->assigned_to === null
                || (int) $ticket->assigned_to === (int) $viewer->id;
        }

        $ids = self::ownerIds($viewer) ?? [];
        $inScope = in_array((int) $ticket->assigned_to, $ids, true)
            || in_array((int) $ticket->created_by, $ids, true);

        if ($inScope) {
            return true;
        }

        return self::level($viewer) === 'team' && $ticket->assigned_to === null;
    }

    // ---------------- Activities (autor o entidad visible) ----------------

    /**
     * @return array<int>|null null = sin restricción.
     */
    public static function visibleIds(User $user, string $class): ?array
    {
        if (self::isUnconstrained($user)) {
            return null;
        }

        return self::visibleRecords($user, $class)->pluck('id')->all();
    }

    public static function scopeActivities(Builder $query, User $user): Builder
    {
        $ids = self::ownerIds($user);

        if ($ids === null) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function ($q) use ($ids, $user, $table) {
            $q->whereIn("{$table}.user_id", $ids);

            foreach ([Company::class, Contact::class, Lead::class, Opportunity::class, Quote::class, Sale::class, Invoice::class] as $class) {
                $visible = self::visibleIds($user, $class);
                $q->orWhere(function ($sq) use ($class, $visible, $table) {
                    $sq->where("{$table}.subjectable_type", $class)
                        ->whereIn("{$table}.subjectable_id", $visible === [] ? [0] : $visible);
                });
            }
        });
    }

    public static function canAccessActivity(User $viewer, Activity $activity): bool
    {
        if (self::isUnconstrained($viewer)) {
            return true;
        }

        $ids = self::ownerIds($viewer) ?? [];

        if (in_array((int) $activity->user_id, $ids, true)) {
            return true;
        }

        return self::canViewModel($viewer, $activity->subjectable);
    }

    // ---------------- Despacho genérico ----------------

    public static function canViewModel(User $viewer, ?Model $model): bool
    {
        if (! $model) {
            return false;
        }

        if (self::isUnconstrained($viewer)) {
            return true;
        }

        return match (true) {
            $model instanceof Task => self::canAccessTask($viewer, $model),
            $model instanceof Activity => self::canAccessActivity($viewer, $model),
            $model instanceof Ticket => self::canAccessTicket($viewer, $model),
            $model instanceof Product, $model instanceof ProductCategory => true,
            default => self::canAccessOwner($viewer, $model->owner ?? null),
        };
    }

    /**
     * Query base de un modelo, ya filtrada por alcance.
     *
     * @param  class-string<Model>  $class
     */
    public static function visibleRecords(User $user, string $class): Builder
    {
        $query = $class::query();

        return match ($class) {
            Task::class => self::scopeTasks($query, $user),
            Ticket::class => self::scopeTickets($query, $user),
            Activity::class => self::scopeActivities($query, $user),
            Product::class, ProductCategory::class => $query,
            default => self::scopeOwned($query, $user),
        };
    }

    /**
     * Valida IDs recibidos por request sin hidratar colecciones completas.
     *
     * @param  class-string<Model>  $class
     */
    public static function isVisibleId(User $user, string $class, mixed $id): bool
    {
        if (blank($id)) {
            return true;
        }

        return self::visibleRecords($user, $class)->whereKey($id)->exists();
    }

    /**
     * @param  class-string<Model>  $class
     */
    public static function assertVisibleId(User $user, string $class, mixed $id): void
    {
        abort_unless(self::isVisibleId($user, $class, $id), 403);
    }

    /** @return array<int, int> */
    public static function filterableUserIds(User $user, ?int $includeId = null): array
    {
        return self::filterableUsers($user, $includeId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function canAssignUser(User $user, mixed $targetId, ?int $includeId = null): bool
    {
        if (blank($targetId)) {
            return true;
        }

        return in_array((int) $targetId, self::filterableUserIds($user, $includeId), true);
    }

    public static function assertCanAssignUser(User $user, mixed $targetId, ?int $includeId = null): void
    {
        abort_unless(self::canAssignUser($user, $targetId, $includeId), 403);
    }

    public static function normalizeOwnerId(User $user, mixed $ownerId, ?int $currentOwnerId = null): ?int
    {
        if (! blank($ownerId)) {
            return (int) $ownerId;
        }

        if ($currentOwnerId !== null) {
            return $currentOwnerId;
        }

        return self::isUnconstrained($user) ? null : (int) $user->id;
    }

    /**
     * Usuarios ofrecibles en filtros/selectores de responsable.
     * Con $includeId se preserva el valor existente aunque esté fuera de
     * alcance (edición sin romper la relación actual).
     */
    public static function filterableUsers(User $user, ?int $includeId = null): Collection
    {
        $level = self::level($user);

        if ($level === 'global') {
            $list = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        } elseif ($level === 'team' && $user->team_id !== null) {
            $list = User::where('team_id', $user->team_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            $list = User::whereKey($user->id)->get(['id', 'name']);
        }

        if ($includeId && ! $list->contains('id', (int) $includeId)) {
            $extra = User::whereKey($includeId)->first(['id', 'name']);
            if ($extra) {
                $list->push($extra);
            }
        }

        return $list;
    }
}
