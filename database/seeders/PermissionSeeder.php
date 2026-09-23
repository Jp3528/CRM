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

    /** Permisos adicionales fuera de la matriz módulo×acción (idempotentes). */
    public const EXTRA = [
        ['name' => 'leads.convert', 'label' => 'Convert Leads', 'description' => 'Permite convertir leads en empresa/contacto (y oportunidad opcional).', 'group' => 'leads'],
    ];

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

        foreach (self::EXTRA as $extra) {
            Permission::firstOrCreate(
                ['name' => $extra['name']],
                [
                    'label' => $extra['label'],
                    'description' => $extra['description'],
                    'group' => $extra['group'],
                ]
            );
        }

        $super = Role::where('name', 'Superadministrador')->first();
        if ($super) {
            $super->permissions()->sync(Permission::pluck('id')->all());
        }
    }
}
