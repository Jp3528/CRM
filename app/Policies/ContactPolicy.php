<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Support\DataScope;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.view')
            && DataScope::canAccessOwner($user, $contact->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.update')
            && DataScope::canAccessOwner($user, $contact->owner);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.delete')
            && DataScope::canAccessOwner($user, $contact->owner);
    }
}
