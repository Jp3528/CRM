<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /** Exactamente los 7 roles de Fase 1. */
    public const ROLES = [
        'Superadministrador',
        'Administrador',
        'Gerente comercial',
        'Supervisor',
        'Vendedor',
        'Soporte',
        'Consulta',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'description' => "Rol {$name} del CRM."]
            );
        }
    }
}
