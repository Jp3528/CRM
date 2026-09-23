<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Support\DataScope;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('quotes.view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.view')
            && DataScope::canAccessOwner($user, $quote->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('quotes.create');
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.update')
            && DataScope::canAccessOwner($user, $quote->owner);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.delete')
            && DataScope::canAccessOwner($user, $quote->owner);
    }
}
