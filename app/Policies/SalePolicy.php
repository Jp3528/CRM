<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Support\DataScope;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->hasPermission('sales.view')
            && DataScope::canAccessOwner($user, $sale->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->hasPermission('sales.update')
            && DataScope::canAccessOwner($user, $sale->owner);
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->hasPermission('sales.delete')
            && DataScope::canAccessOwner($user, $sale->owner);
    }
}
