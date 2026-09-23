<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_boots(): void
    {
        $this->assertNotEmpty(config('app.name'));
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_phase_one_tables_exist(): void
    {
        foreach ([
            'users', 'teams', 'roles', 'permissions',
            'role_user', 'permission_role', 'permission_user',
            'companies', 'contacts', 'leads',
            'pipelines', 'pipeline_stages',
            'opportunities', 'opportunity_stage_history',
            'activities', 'tasks', 'audit_logs', 'settings',
            'tags', 'taggables',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function test_roles_seeder_creates_exactly_seven_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(7, Role::count());
        foreach (RoleSeeder::ROLES as $name) {
            $this->assertTrue(Role::where('name', $name)->exists(), "Falta el rol {$name}");
        }
    }

    public function test_pipeline_seeder_creates_ventas_with_seven_stages(): void
    {
        $this->seed(PipelineSeeder::class);

        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $stages = $pipeline->stages()->orderBy('position')->get();

        $this->assertSame(
            ['Prospecto', 'Contactado', 'Necesidad identificada', 'Propuesta', 'Negociación', 'Ganada', 'Perdida'],
            $stages->pluck('name')->all()
        );
        $this->assertTrue($stages->where('name', 'Ganada')->first()->is_won);
        $this->assertTrue($stages->where('name', 'Perdida')->first()->is_lost);
        $this->assertSame(0, $stages->where('is_won', true)->where('is_lost', true)->count());
    }

    public function test_factories_and_core_relationships(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);

        $team = Team::factory()->create();
        $user = User::factory()->create(['team_id' => $team->id]);
        $this->assertSame($team->id, $user->team->id);

        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($company->contacts->contains($contact));
        $this->assertSame($company->id, $contact->company->id);

        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();

        $opportunity = Opportunity::factory()->create([
            'owner_id' => $user->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'lead_id' => $lead->id,
        ]);

        $this->assertSame($pipeline->id, $opportunity->pipeline->id);
        $this->assertSame($stage->id, $opportunity->stage->id);
        $this->assertSame($company->id, $opportunity->company->id);
        $this->assertIsNumeric($opportunity->amount);
        $this->assertIsNumeric($lead->estimated_value);
    }
}
