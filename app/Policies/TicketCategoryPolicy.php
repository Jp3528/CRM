<?php

namespace App\Policies;

use App\Models\TicketCategory;
use App\Models\User;

class TicketCategoryPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->hasPermission('categories.view') || $actor->hasPermission('tickets.view'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function view(User $actor, TicketCategory $category): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return ($actor->hasPermission('categories.create') || $actor->hasPermission('tickets.create'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function update(User $actor, TicketCategory $category): bool
    {
        return ($actor->hasPermission('categories.update') || $actor->hasPermission('tickets.update'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function delete(User $actor, TicketCategory $category): bool
    {
        return ($actor->hasPermission('categories.delete') || $actor->hasPermission('tickets.delete'))
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }
}
