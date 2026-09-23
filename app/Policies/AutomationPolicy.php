<?php

namespace App\Policies;

use App\Models\Automation;
use App\Models\User;
use App\Support\DataScope;

class AutomationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('automations.view');
    }

    public function view(User $user, Automation $automation): bool
    {
        return $user->hasPermission('automations.view')
            && DataScope::canAccessOwner($user, $automation->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('automations.create');
    }

    public function update(User $user, Automation $automation): bool
    {
        return $user->hasPermission('automations.update')
            && DataScope::canAccessOwner($user, $automation->owner);
    }

    public function delete(User $user, Automation $automation): bool
    {
        return $user->hasPermission('automations.delete')
            && DataScope::canAccessOwner($user, $automation->owner);
    }

    public function execute(User $user, Automation $automation): bool
    {
        return $user->hasPermission('automations.execute')
            && DataScope::canAccessOwner($user, $automation->owner);
    }
}
