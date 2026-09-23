<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.update');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.delete');
    }
}
