<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $currentUser = $request->user();
        $roles = Role::with('permissions:id,name,group')->orderBy('id')->get();
        $permissionsByGroup = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');

        $sampleRole = $roles->first() ?? new Role;
        $canUpdate = $currentUser->can('update', $sampleRole);

        return view('roles.index', [
            'roles' => $roles,
            'permissionsByGroup' => $permissionsByGroup,
            'canUpdate' => $canUpdate,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $permissionIds = $validated['permissions'] ?? [];

        // El Superadministrador conserva siempre todos los permisos
        if ($role->name === 'Superadministrador') {
            $permissionIds = Permission::pluck('id')->all();
        }

        $role->permissions()->sync($permissionIds);
        DataScope::clearCache();

        return redirect()->route('roles.index')
            ->with('status', "Matriz de permisos actualizada para el rol '{$role->name}'.");
    }

    /**
     * Restablece los permisos por defecto de un rol específico.
     */
    public function reset(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $defaultPerms = match ($role->name) {
            'Superadministrador' => Permission::pluck('name')->all(),
            'Administrador' => Permission::where('name', '!=', 'roles.update')->pluck('name')->all(),
            'Gerente comercial' => Permission::whereIn('group', [
                'companies', 'contacts', 'leads', 'opportunities', 'quotes', 'sales',
                'invoices', 'tasks', 'activities', 'reports', 'campaigns', 'communications',
            ])->pluck('name')->merge(['exports.view', 'leads.convert'])->all(),
            'Supervisor' => Permission::whereIn('group', [
                'companies', 'contacts', 'leads', 'opportunities', 'quotes', 'sales',
                'tickets', 'tasks', 'activities', 'teams',
            ])->where('name', 'not like', 'teams.create')->where('name', 'not like', 'teams.delete')
                ->pluck('name')->merge(['reports.view', 'exports.view', 'leads.convert'])->all(),
            'Vendedor' => [
                'companies.view', 'companies.create', 'companies.update',
                'contacts.view', 'contacts.create', 'contacts.update',
                'leads.view', 'leads.create', 'leads.update', 'leads.convert',
                'opportunities.view', 'opportunities.create', 'opportunities.update',
                'quotes.view', 'quotes.create', 'quotes.update',
                'sales.view', 'sales.create',
                'tasks.view', 'tasks.create', 'tasks.update',
                'activities.view', 'activities.create', 'activities.update',
            ],
            'Soporte' => [
                'tickets.view', 'tickets.create', 'tickets.update',
                'companies.view', 'contacts.view',
                'tasks.view', 'tasks.create', 'tasks.update',
                'activities.view', 'activities.create', 'activities.update',
            ],
            'Consulta' => Permission::where('name', 'like', '%.view')->pluck('name')->all(),
            default => [],
        };

        $ids = Permission::whereIn('name', $defaultPerms)->pluck('id')->all();
        $role->permissions()->sync($ids);
        DataScope::clearCache();

        return redirect()->route('roles.index')
            ->with('status', "Permisos del rol '{$role->name}' restablecidos a los valores predeterminados.");
    }
}
