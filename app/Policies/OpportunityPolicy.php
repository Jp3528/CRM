<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\DataScope;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('opportunities.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.view')
            && DataScope::canAccessOwner($user, $opportunity->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('opportunities.create');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.update')
            && DataScope::canAccessOwner($user, $opportunity->owner);
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.delete')
            && DataScope::canAccessOwner($user, $opportunity->owner);
    }

    public function move(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.update')
            && DataScope::canAccessOwner($user, $opportunity->owner);
    }
}
