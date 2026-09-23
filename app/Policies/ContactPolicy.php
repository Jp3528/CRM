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

    /**
     * Alcance por propietario + coherencia con la empresa: si el contacto
     * pertenece a una empresa fuera del alcance, se deniega.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.view')
            && DataScope::canAccessOwner($user, $contact->owner)
            && ($contact->company_id === null || DataScope::canViewModel($user, $contact->company));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.update')
            && DataScope::canAccessOwner($user, $contact->owner)
            && ($contact->company_id === null || DataScope::canViewModel($user, $contact->company));
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.delete')
            && DataScope::canAccessOwner($user, $contact->owner)
            && ($contact->company_id === null || DataScope::canViewModel($user, $contact->company));
    }
}
