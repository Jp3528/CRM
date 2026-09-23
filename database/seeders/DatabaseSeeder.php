<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class, ProductSeeder::class, TicketCategorySeeder::class]);

        if (app()->environment('testing')) {
            return;
        }

        // Datos de desarrollo: nunca en producción.
        if (app()->environment('production')) {
            return;
        }

        $this->call(DemoUserSeeder::class);

        $user = User::where('email', DemoUserSeeder::EMAIL)->firstOrFail();

        $companies = Company::factory(3)->create(['owner_id' => $user->id]);
        foreach ($companies as $company) {
            Contact::factory(2)->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        }
        Lead::factory(3)->create(['owner_id' => $user->id]);
    }
}
