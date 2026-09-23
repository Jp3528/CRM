<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use App\Support\DataScope;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.view')
            && DataScope::canAccessOwner($user, $campaign->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.update')
            && DataScope::canAccessOwner($user, $campaign->owner);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->hasPermission('campaigns.delete')
            && DataScope::canAccessOwner($user, $campaign->owner);
    }
}
