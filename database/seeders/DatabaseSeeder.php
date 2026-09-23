<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);

        if (app()->environment('testing')) {
            return;
        }

        $team = Team::firstOrCreate(
            ['slug' => 'equipo-comercial'],
            ['name' => 'Equipo comercial', 'description' => 'Equipo demo de Fase 1.', 'status' => 'active']
        );

        $user = User::firstOrCreate(
            ['email' => 'demo@nexuscrm.local'],
            ['name' => 'Usuario Demo', 'password' => 'password', 'team_id' => $team->id, 'status' => 'active']
        );

        $companies = Company::factory(3)->create(['owner_id' => $user->id]);
        foreach ($companies as $company) {
            Contact::factory(2)->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        }
        Lead::factory(3)->create(['owner_id' => $user->id]);
    }
}
