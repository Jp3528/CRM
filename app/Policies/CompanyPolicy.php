<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\DataScope;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.view')
            && DataScope::canAccessOwner($user, $company->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.update')
            && DataScope::canAccessOwner($user, $company->owner);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.delete')
            && DataScope::canAccessOwner($user, $company->owner);
    }
}
