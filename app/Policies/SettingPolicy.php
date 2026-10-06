<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('settings.view')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }

    public function view(User $actor, ?Setting $setting = null): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, ?Setting $setting = null): bool
    {
        return $actor->hasPermission('settings.update')
            && ($actor->hasRole('Superadministrador') || $actor->hasRole('Administrador'));
    }
}
