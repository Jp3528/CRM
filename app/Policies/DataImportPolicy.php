<?php

namespace App\Policies;

use App\Models\DataImport;
use App\Models\User;
use App\Support\DataScope;
use App\Support\ImportCatalog;

class DataImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('imports.view');
    }

    public function view(User $user, DataImport $import): bool
    {
        return $user->hasPermission('imports.view')
            && DataScope::canAccessCreatedBy($user, $import->created_by);
    }

    public function create(User $user, ?string $module = null): bool
    {
        if (DataScope::isReadOnly($user)) {
            return false;
        }

        if (! $user->hasPermission('imports.create')) {
            return false;
        }

        if ($module !== null && in_array($module, ImportCatalog::MODULES, true)) {
            $required = ImportCatalog::requiredPermission($module);
            if (! $user->hasPermission($required)) {
                return false;
            }
        }

        return true;
    }

    public function downloadErrors(User $user, DataImport $import): bool
    {
        return $this->view($user, $import);
    }
}
