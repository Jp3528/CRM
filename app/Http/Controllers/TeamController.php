<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Team::class);

        $currentUser = $request->user();
        $isGlobal = $currentUser->hasRole('Superadministrador') || $currentUser->hasRole('Administrador');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $query = Team::query()->withCount([
            'users as active_members_count' => fn ($q) => $q->where('status', 'active'),
            'users as total_members_count',
        ]);

        // Si no es rol global, supervisor solo ve su equipo asignado
        if (! $isGlobal) {
            if ($currentUser->team_id) {
                $query->where('id', $currentUser->team_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($validated['search'])) {
            $term = mb_strtolower(trim($validated['search']));
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$term}%"]);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $teams = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('teams.index', [
            'teams' => $teams,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
            'canCreate' => $currentUser->can('create', Team::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Team::class);

        return view('teams.create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

        return redirect()->route('teams.show', $team)
            ->with('status', "Equipo '{$team->name}' creado correctamente.");
    }

    public function show(Request $request, Team $team): View
    {
        $this->authorize('view', $team);

        $currentUser = $request->user();
        $members = $team->users()->with('roles')->orderBy('name')->get();

        // Usuarios activos candidatos para ser agregados a este equipo
        $canAssign = $currentUser->can('assign', $team);
        $candidates = $canAssign
            ? User::where('status', 'active')
                ->where(function ($q) use ($team) {
                    $q->whereNull('team_id')
                        ->orWhere('team_id', '!=', $team->id);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'team_id'])
            : collect();

        return view('teams.show', [
            'team' => $team,
            'members' => $members,
            'candidates' => $candidates,
            'canUpdate' => $currentUser->can('update', $team),
            'canDelete' => $currentUser->can('delete', $team),
            'canAssign' => $canAssign,
        ]);
    }

    public function edit(Team $team): View
    {
        $this->authorize('update', $team);

        return view('teams.edit', [
            'team' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());
        DataScope::clearCache();

        return redirect()->route('teams.show', $team)
            ->with('status', "Equipo '{$team->name}' actualizado correctamente.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if ($team->users()->exists()) {
            throw ValidationException::withMessages([
                'team' => 'No es posible eliminar un equipo que aún tiene miembros asignados. Reasigna o retira a sus integrantes primero.',
            ]);
        }

        $teamName = $team->name;
        $team->delete();
        DataScope::clearCache();

        return redirect()->route('teams.index')
            ->with('status', "Equipo '{$teamName}' eliminado.");
    }

    /**
     * Asigna un usuario al equipo y limpia la caché de alcance inmediatamente.
     */
    public function assignMember(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('assign', $team);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::where('status', 'active')->findOrFail($validated['user_id']);
        $previousTeam = $user->team?->name;

        $user->update(['team_id' => $team->id]);
        DataScope::clearCache();

        $msg = "Usuario {$user->name} asignado al equipo '{$team->name}'.";
        if ($previousTeam) {
            $msg .= " Su pertenencia previa al equipo '{$previousTeam}' fue transferida y el alcance de sus registros se actualizó de inmediato.";
        }

        return redirect()->route('teams.show', $team)->with('status', $msg);
    }

    /**
     * Retira a un usuario del equipo (dejándolo con team_id null).
     */
    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('assign', $team);

        if ((int) $user->team_id !== (int) $team->id) {
            return redirect()->route('teams.show', $team);
        }

        $user->update(['team_id' => null]);
        DataScope::clearCache();

        return redirect()->route('teams.show', $team)
            ->with('status', "Usuario {$user->name} retirado del equipo '{$team->name}'.");
    }
}
