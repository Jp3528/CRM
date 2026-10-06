<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('teams.view') || $actor->hasPermission('users.view');
    }

    public function view(User $actor, Team $team): bool
    {
        if (! $actor->hasPermission('teams.view') && ! $actor->hasPermission('users.view')) {
            return false;
        }

        // Roles globales pueden ver cualquier equipo; supervisores solo su propio equipo
        if ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador')) {
            return true;
        }

        return $actor->team_id !== null && (int) $actor->team_id === (int) $team->id;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('teams.create')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function update(User $actor, Team $team): bool
    {
        return $actor->hasPermission('teams.update')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function delete(User $actor, Team $team): bool
    {
        return $actor->hasPermission('teams.delete')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function assign(User $actor, Team $team): bool
    {
        return $actor->hasPermission('teams.assign')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }
}
