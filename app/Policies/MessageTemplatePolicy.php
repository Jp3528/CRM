<?php

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\DataScope;

class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('templates.view');
    }

    public function view(User $user, MessageTemplate $template): bool
    {
        return $user->hasPermission('templates.view')
            && DataScope::canAccessOwner($user, $template->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('templates.create');
    }

    public function update(User $user, MessageTemplate $template): bool
    {
        return $user->hasPermission('templates.update')
            && DataScope::canAccessOwner($user, $template->owner);
    }

    public function delete(User $user, MessageTemplate $template): bool
    {
        return $user->hasPermission('templates.delete')
            && DataScope::canAccessOwner($user, $template->owner);
    }
}
