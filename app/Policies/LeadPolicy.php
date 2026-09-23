<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Support\DataScope;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.view')
            && DataScope::canAccessOwner($user, $lead->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.update')
            && DataScope::canAccessOwner($user, $lead->owner);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.delete')
            && DataScope::canAccessOwner($user, $lead->owner);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.convert')
            && DataScope::canAccessOwner($user, $lead->owner);
    }
}
