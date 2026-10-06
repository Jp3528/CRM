<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('users.update')) {
            return false;
        }

        // Un administrador normal no puede modificar a un Superadministrador
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('users.delete')) {
            return false;
        }

        // Nadie puede eliminarse a sí mismo
        if ((int) $actor->id === (int) $target->id) {
            return false;
        }

        // Solo un Superadministrador puede eliminar a otro
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function assignAccess(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('users.assign_access') && ! $actor->isSuperAdmin()) {
            return false;
        }

        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return false;
        }

        return true;
    }
}
