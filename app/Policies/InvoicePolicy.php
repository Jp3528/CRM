<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\DataScope;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view')
            && DataScope::canAccessOwner($user, $invoice->owner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.update')
            && DataScope::canAccessOwner($user, $invoice->owner);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.delete')
            && DataScope::canAccessOwner($user, $invoice->owner);
    }
}
