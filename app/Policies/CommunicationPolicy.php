<?php

namespace App\Policies;

use App\Models\Communication;
use App\Models\User;
use App\Support\DataScope;

class CommunicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('communications.view');
    }

    public function view(User $user, Communication $communication): bool
    {
        return $user->hasPermission('communications.view')
            && DataScope::canAccessCommunication($user, $communication);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('communications.create');
    }

    public function update(User $user, Communication $communication): bool
    {
        return $user->hasPermission('communications.update')
            && DataScope::canAccessCommunication($user, $communication);
    }

    public function delete(User $user, Communication $communication): bool
    {
        return $user->hasPermission('communications.delete')
            && DataScope::canAccessCommunication($user, $communication);
    }
}
