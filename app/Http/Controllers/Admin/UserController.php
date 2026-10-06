<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Users\UserProtectionService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected UserProtectionService $protection = new UserProtectionService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'team_id' => ['nullable', 'string'],
            'role_id' => ['nullable', 'integer'],
        ]);

        $query = User::query()->with(['team:id,name', 'roles:id,name,slug']);

        if (! empty($validated['search'])) {
            $term = mb_strtolower(trim($validated['search']));
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"]);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['team_id'])) {
            if ($validated['team_id'] === 'none') {
                $query->whereNull('team_id');
            } else {
                $query->where('team_id', (int) $validated['team_id']);
            }
        }

        if (! empty($validated['role_id'])) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.id', $validated['role_id']));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $teams = Team::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $roles = Role::orderBy('name')->get(['id', 'name']);

        return view('admin.users.index', [
            'users' => $users,
            'teams' => $teams,
            'roles' => $roles,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'team_id' => $validated['team_id'] ?? '',
                'role_id' => $validated['role_id'] ?? '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        $currentUser = $request->user();
        $teams = Team::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        // Si el usuario actual no es Superadministrador, no puede conceder Superadministrador
        $rolesQuery = Role::orderBy('name');
        if (! $currentUser->isSuperAdmin()) {
            $rolesQuery->where('name', '!=', 'Superadministrador');
        }
        $roles = $rolesQuery->get(['id', 'name', 'description']);

        return view('admin.users.create', [
            'teams' => $teams,
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $currentUser = $request->user();
        $data = $request->validated();
        $roleIds = $data['roles'] ?? [];

        // Validar que no se intente asignar Superadministrador si el actor no lo es
        if (! empty($roleIds)) {
            $roles = Role::whereIn('id', $roleIds)->get();
            foreach ($roles as $role) {
                $this->protection->assertCanAssignRole($currentUser, $role);
            }
        }

        $user = DB::transaction(function () use ($data, $roleIds) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'team_id' => $data['team_id'] ?? null,
                'status' => $data['status'],
            ]);

            if (! empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }

            return $user;
        });

        return redirect()->route('admin.users.show', $user)
            ->with('status', "Usuario {$user->name} creado correctamente.");
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['team', 'roles.permissions', 'permissions']);

        // Conteos comerciales protegidos
        $stats = [
            'companies' => $user->ownedCompanies()->count(),
            'contacts' => $user->ownedContacts()->count(),
            'leads' => $user->ownedLeads()->count(),
            'opportunities' => $user->ownedOpportunities()->count(),
            'tasks' => $user->assignedTasks()->count(),
        ];

        return view('admin.users.show', [
            'user' => $user,
            'stats' => $stats,
            'canUpdate' => request()->user()->can('update', $user),
            'canDelete' => request()->user()->can('delete', $user),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        $currentUser = $request->user();
        $this->protection->assertCanModifyUser($currentUser, $user);

        $teams = Team::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        $rolesQuery = Role::orderBy('name');
        if (! $currentUser->isSuperAdmin()) {
            $rolesQuery->where('name', '!=', 'Superadministrador');
        }
        $roles = $rolesQuery->get(['id', 'name', 'description']);

        return view('admin.users.edit', [
            'user' => $user,
            'teams' => $teams,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        $this->protection->assertCanModifyUser($currentUser, $user);

        $data = $request->validated();
        $newStatus = $data['status'];
        $roleIds = $data['roles'] ?? [];
        $teamChanged = (int) ($user->team_id ?? 0) !== (int) ($data['team_id'] ?? 0);

        DB::transaction(function () use ($currentUser, $user, $data, $newStatus, $roleIds) {
            // Protección de estado (último superadministrador)
            if ($newStatus !== $user->status) {
                $this->protection->updateStatus($currentUser, $user, $newStatus);
            }

            // Protección de roles
            if (isset($data['roles'])) {
                $this->protection->syncRoles($currentUser, $user, $roleIds);
            }

            $updatePayload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'team_id' => $data['team_id'] ?? null,
                'status' => $newStatus,
            ];

            if (! empty($data['password'])) {
                $updatePayload['password'] = Hash::make($data['password']);
            }

            $user->update($updatePayload);
        });

        if ($teamChanged) {
            DataScope::clearCache();
        }

        return redirect()->route('admin.users.show', $user)
            ->with('status', "Usuario {$user->name} actualizado correctamente.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $this->protection->assertCanDeleteUser($request->user(), $user);

        DB::transaction(function () use ($user) {
            // Protección de último Superadministrador
            $this->protection->assertNotLastActiveSuperAdmin($user, 'eliminar');

            // Soft delete seguro (preserva registros comerciales vinculados)
            $user->delete();
        });

        DataScope::clearCache();

        return redirect()->route('admin.users.index')
            ->with('status', "Usuario {$user->name} eliminado del sistema.");
    }
}
