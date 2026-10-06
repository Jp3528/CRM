<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function update(User $actor, Role $role): bool
    {
        // Solo Superadministrador modifica la matriz de roles y permisos
        return $actor->isSuperAdmin() && $actor->hasPermission('roles.update');
    }
}
