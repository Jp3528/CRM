<?php

namespace App\Services\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserProtectionService
{
    /**
     * Valida que el actor tenga jerarquía para modificar al usuario objetivo.
     * Un Administrador NO puede modificar, editar ni desactivar a un Superadministrador.
     */
    public function assertCanModifyUser(User $actor, User $target): void
    {
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'No tienes autorización para modificar a un Superadministrador.',
            ]);
        }
    }

    /**
     * Valida que el actor tenga jerarquía para eliminar al usuario objetivo.
     * Nadie puede eliminarse a sí mismo, y solo un Superadministrador puede eliminar a otro (si no es el último).
     */
    public function assertCanDeleteUser(User $actor, User $target): void
    {
        if ((int) $actor->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminar tu propia cuenta de usuario.',
            ]);
        }

        $this->assertCanModifyUser($actor, $target);
        $this->assertNotLastActiveSuperAdmin($target, 'eliminar');
    }

    /**
     * Valida que el actor no intente escalar privilegios asignando roles superiores.
     * Solo un Superadministrador puede otorgar el rol de Superadministrador.
     */
    public function assertCanAssignRole(User $actor, Role|string $role): void
    {
        $roleName = $role instanceof Role ? $role->name : $role;

        if ($roleName === 'Superadministrador' && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'roles' => 'Solo un Superadministrador puede otorgar el rol de Superadministrador.',
            ]);
        }
    }

    /**
     * Valida que el actor tenga permiso para otorgar permisos directos o editar la matriz.
     * Solo Superadministrador puede editar permisos directos de usuarios.
     */
    public function assertCanAssignPermissions(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'permissions' => 'Solo un Superadministrador puede configurar permisos directos.',
            ]);
        }
    }

    /**
     * Protección atómica contra eliminación, desactivación o degradación del último
     * Superadministrador activo mediante bloqueo pesimista en base de datos.
     */
    public function assertNotLastActiveSuperAdmin(User $target, string $action = 'modificar'): void
    {
        if (! $target->isSuperAdmin() || $target->status !== 'active') {
            return;
        }

        $activeSuperAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'Superadministrador'))
            ->where('status', 'active')
            ->lockForUpdate()
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages([
                'status' => "Operación bloqueada: No se puede {$action} al único Superadministrador activo del sistema.",
            ]);
        }
    }

    /**
     * Aplica la actualización de estado garantizando que no se desactive al último Superadministrador.
     */
    public function updateStatus(User $actor, User $target, string $newStatus): void
    {
        $this->assertCanModifyUser($actor, $target);

        if ($newStatus === 'inactive' && $target->isActive()) {
            $this->assertNotLastActiveSuperAdmin($target, 'desactivar');
        }

        $target->update(['status' => $newStatus]);
    }

    /**
     * Asigna roles a un usuario protegiendo contra escalamiento de privilegios y retiro
     * del rol de Superadministrador al último existente.
     *
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRoles(User $actor, User $target, array $roleIds): void
    {
        $this->assertCanModifyUser($actor, $target);

        $superRole = Role::where('name', 'Superadministrador')->first();
        $isAssigningSuper = $superRole && in_array($superRole->id, $roleIds, false);
        $wasSuper = $target->isSuperAdmin();

        // Si se intenta asignar Superadministrador y el actor no lo es
        if ($isAssigningSuper && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'roles' => 'Solo un Superadministrador puede conceder el rol de Superadministrador.',
            ]);
        }

        // Si se intenta quitar el rol de Superadministrador
        if ($wasSuper && ! $isAssigningSuper) {
            $this->assertNotLastActiveSuperAdmin($target, 'quitar el rol de Superadministrador a');
        }

        $target->roles()->sync($roleIds);
    }
}
