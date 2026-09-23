<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario demo de desarrollo (solo entornos no productivos).
 * Idempotente: no duplica usuarios ni roles.
 */
class DemoUserSeeder extends Seeder
{
    public const EMAIL = 'demo@nexuscrm.local';

    public function run(): void
    {
        $team = Team::firstOrCreate(
            ['slug' => 'equipo-comercial'],
            ['name' => 'Equipo comercial', 'description' => 'Equipo demo.', 'status' => 'active']
        );

        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            // Contraseña demo solo para desarrollo local (ver README).
            ['name' => 'Usuario Demo', 'password' => 'password', 'team_id' => $team->id, 'status' => 'active']
        );

        $super = Role::where('name', 'Superadministrador')->first();
        if ($super) {
            $user->roles()->syncWithoutDetaching([$super->id]);
        }
    }
}
