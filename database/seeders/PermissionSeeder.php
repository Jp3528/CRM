<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /** Permisos base predecibles por módulo. */
    public const GROUPS = ['users', 'companies', 'contacts', 'leads', 'opportunities', 'tasks'];

    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        foreach (self::GROUPS as $group) {
            foreach (self::ACTIONS as $action) {
                Permission::firstOrCreate(
                    ['name' => "{$group}.{$action}"],
                    [
                        'label' => ucfirst($action).' '.ucfirst($group),
                        'description' => "Permite {$action} en {$group}.",
                        'group' => $group,
                    ]
                );
            }
        }

        $super = Role::where('name', 'Superadministrador')->first();
        if ($super) {
            $super->permissions()->sync(Permission::pluck('id')->all());
        }
    }
}
