<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.view')
            && DataScope::canAccessTicket($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.update')
            && DataScope::canAccessTicket($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.delete')
            && DataScope::canAccessTicket($user, $ticket);
    }
}
