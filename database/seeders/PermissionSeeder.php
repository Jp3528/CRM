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
        ['name' => 'activities.view', 'label' => 'View Activities', 'description' => 'Permite ver actividades.', 'group' => 'activities'],
        ['name' => 'activities.create', 'label' => 'Create Activities', 'description' => 'Permite registrar actividades manuales.', 'group' => 'activities'],
        ['name' => 'activities.update', 'label' => 'Update Activities', 'description' => 'Permite editar actividades manuales.', 'group' => 'activities'],
        ['name' => 'activities.delete', 'label' => 'Delete Activities', 'description' => 'Permite eliminar actividades manuales.', 'group' => 'activities'],
        ['name' => 'products.view', 'label' => 'View Products', 'description' => 'Permite ver productos.', 'group' => 'products'],
        ['name' => 'products.create', 'label' => 'Create Products', 'description' => 'Permite crear productos.', 'group' => 'products'],
        ['name' => 'products.update', 'label' => 'Update Products', 'description' => 'Permite editar productos.', 'group' => 'products'],
        ['name' => 'products.delete', 'label' => 'Delete Products', 'description' => 'Permite eliminar productos.', 'group' => 'products'],
        ['name' => 'quotes.view', 'label' => 'View Quotes', 'description' => 'Permite ver cotizaciones.', 'group' => 'quotes'],
        ['name' => 'quotes.create', 'label' => 'Create Quotes', 'description' => 'Permite crear cotizaciones.', 'group' => 'quotes'],
        ['name' => 'quotes.update', 'label' => 'Update Quotes', 'description' => 'Permite editar cotizaciones y cambiar su estado.', 'group' => 'quotes'],
        ['name' => 'quotes.delete', 'label' => 'Delete Quotes', 'description' => 'Permite eliminar cotizaciones.', 'group' => 'quotes'],
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
