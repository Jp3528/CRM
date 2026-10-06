<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determina si el usuario puede consultar el listado de auditoría.
     * Solo administradores globales con permiso audit.view.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAccessAudit($user);
    }

    /**
     * Determina si el usuario puede ver un registro individual de auditoría.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->canAccessAudit($user);
    }

    /**
     * Las bitácoras de auditoría son inmutables.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    protected function canAccessAudit(User $user): bool
    {
        if (! $user->hasRole('Superadministrador') && ! $user->hasRole('Administrador')) {
            return false;
        }

        return $user->hasPermission('audit.view');
    }
}
