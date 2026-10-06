<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /** Permisos base predecibles por módulo. */
    public const GROUPS = ['users', 'companies', 'contacts', 'leads', 'opportunities', 'tasks', 'teams', 'roles', 'settings', 'pipelines'];

    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    /** Permisos adicionales fuera de la matriz módulo×acción (idempotentes). */
    public const EXTRA = [
        ['name' => 'users.assign_access', 'label' => 'Assign User Access', 'description' => 'Permite asignar roles y permisos a usuarios.', 'group' => 'users'],
        ['name' => 'teams.assign', 'label' => 'Assign Team Members', 'description' => 'Permite asignar o mover usuarios de equipo.', 'group' => 'teams'],
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
        ['name' => 'sales.view', 'label' => 'View Sales', 'description' => 'Permite ver ventas.', 'group' => 'sales'],
        ['name' => 'sales.create', 'label' => 'Create Sales', 'description' => 'Permite crear ventas y convertir cotizaciones.', 'group' => 'sales'],
        ['name' => 'sales.update', 'label' => 'Update Sales', 'description' => 'Permite editar ventas y cambiar su estado.', 'group' => 'sales'],
        ['name' => 'sales.delete', 'label' => 'Delete Sales', 'description' => 'Permite eliminar ventas.', 'group' => 'sales'],
        ['name' => 'invoices.view', 'label' => 'View Invoices', 'description' => 'Permite ver facturas internas.', 'group' => 'invoices'],
        ['name' => 'invoices.create', 'label' => 'Create Invoices', 'description' => 'Permite generar facturas internas desde ventas.', 'group' => 'invoices'],
        ['name' => 'invoices.update', 'label' => 'Update Invoices', 'description' => 'Permite cambiar el estado de facturas internas.', 'group' => 'invoices'],
        ['name' => 'invoices.delete', 'label' => 'Delete Invoices', 'description' => 'Permite eliminar facturas internas.', 'group' => 'invoices'],
        ['name' => 'tickets.view', 'label' => 'View Tickets', 'description' => 'Permite ver tickets de soporte.', 'group' => 'tickets'],
        ['name' => 'tickets.create', 'label' => 'Create Tickets', 'description' => 'Permite crear tickets de soporte.', 'group' => 'tickets'],
        ['name' => 'tickets.update', 'label' => 'Update Tickets', 'description' => 'Permite editar tickets, asignar y cambiar su estado.', 'group' => 'tickets'],
        ['name' => 'tickets.delete', 'label' => 'Delete Tickets', 'description' => 'Permite eliminar tickets de soporte.', 'group' => 'tickets'],
        // Fase 10 — Campañas y comunicaciones (envíos siempre simulados, sin proveedor externo).
        // Plantillas usan el namespace corto `templates.*` (documentado en README).
        ['name' => 'campaigns.view', 'label' => 'View Campaigns', 'description' => 'Permite ver campañas de marketing.', 'group' => 'campaigns'],
        ['name' => 'campaigns.create', 'label' => 'Create Campaigns', 'description' => 'Permite crear campañas.', 'group' => 'campaigns'],
        ['name' => 'campaigns.update', 'label' => 'Update Campaigns', 'description' => 'Permite editar campañas y gestionar miembros.', 'group' => 'campaigns'],
        ['name' => 'campaigns.delete', 'label' => 'Delete Campaigns', 'description' => 'Permite eliminar campañas.', 'group' => 'campaigns'],
        ['name' => 'templates.view', 'label' => 'View Templates', 'description' => 'Permite ver plantillas de mensajes.', 'group' => 'templates'],
        ['name' => 'templates.create', 'label' => 'Create Templates', 'description' => 'Permite crear plantillas de mensajes.', 'group' => 'templates'],
        ['name' => 'templates.update', 'label' => 'Update Templates', 'description' => 'Permite editar plantillas de mensajes.', 'group' => 'templates'],
        ['name' => 'templates.delete', 'label' => 'Delete Templates', 'description' => 'Permite eliminar plantillas de mensajes.', 'group' => 'templates'],
        ['name' => 'communications.view', 'label' => 'View Communications', 'description' => 'Permite ver comunicaciones internas/simuladas.', 'group' => 'communications'],
        ['name' => 'communications.create', 'label' => 'Create Communications', 'description' => 'Permite registrar comunicaciones simuladas.', 'group' => 'communications'],
        ['name' => 'communications.update', 'label' => 'Update Communications', 'description' => 'Permite editar borradores y registrar envíos simulados.', 'group' => 'communications'],
        ['name' => 'communications.delete', 'label' => 'Delete Communications', 'description' => 'Permite eliminar comunicaciones.', 'group' => 'communications'],
        // Fase 11 — Motor de automatizaciones internas (sin código arbitrario ni envíos).
        ['name' => 'automations.view', 'label' => 'View Automations', 'description' => 'Permite ver automatizaciones.', 'group' => 'automations'],
        ['name' => 'automations.create', 'label' => 'Create Automations', 'description' => 'Permite crear automatizaciones en borrador.', 'group' => 'automations'],
        ['name' => 'automations.update', 'label' => 'Update Automations', 'description' => 'Permite editar automatizaciones pausadas o en borrador.', 'group' => 'automations'],
        ['name' => 'automations.delete', 'label' => 'Delete Automations', 'description' => 'Permite eliminar automatizaciones.', 'group' => 'automations'],
        ['name' => 'automations.execute', 'label' => 'Execute Automations', 'description' => 'Permite activar, pausar y probar automatizaciones.', 'group' => 'automations'],
        // Fase 12 — Reportes y forecast (solo lectura; el alcance lo da DataScope).
        ['name' => 'reports.view', 'label' => 'View Reports', 'description' => 'Permite ver el centro de reportes.', 'group' => 'reports'],
        ['name' => 'reports.forecast', 'label' => 'View Forecast', 'description' => 'Permite ver el forecast comercial ponderado.', 'group' => 'reports'],
        // Fase 13 — Importaciones y exportaciones de datos.
        ['name' => 'imports.view', 'label' => 'View Imports', 'description' => 'Permite ver el historial y estado de importaciones.', 'group' => 'imports'],
        ['name' => 'imports.create', 'label' => 'Create Imports', 'description' => 'Permite subir, previsualizar y confirmar importaciones de datos.', 'group' => 'imports'],
        ['name' => 'exports.view', 'label' => 'Export Data', 'description' => 'Permite exportar listados y reportes a CSV, XLSX o PDF.', 'group' => 'exports'],
        // Fase 15 — Configuración, pipelines y catálogos.
        ['name' => 'pipelines.archive', 'label' => 'Archive Pipelines', 'description' => 'Permite archivar pipelines y etapas.', 'group' => 'pipelines'],
        ['name' => 'categories.view', 'label' => 'View Categories', 'description' => 'Permite ver catálogos de categorías.', 'group' => 'categories'],
        ['name' => 'categories.create', 'label' => 'Create Categories', 'description' => 'Permite crear categorías de productos y tickets.', 'group' => 'categories'],
        ['name' => 'categories.update', 'label' => 'Update Categories', 'description' => 'Permite editar y archivar categorías.', 'group' => 'categories'],
        ['name' => 'categories.delete', 'label' => 'Delete Categories', 'description' => 'Permite eliminar categorías no utilizadas.', 'group' => 'categories'],
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
