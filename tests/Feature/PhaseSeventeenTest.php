<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Role;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 17 — Diseño Profesional y Experiencia Responsive.
 * P17A: Sistema visual, navegación organizada y drawer accesible.
 * P17B: Formularios consistentes y alternativa táctil al arrastre del Kanban.
 * P17C: Búsqueda global acotada a entidades autorizadas y aislamiento DataScope.
 */
class PhaseSeventeenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, SettingSeeder::class, PipelineSeeder::class]);
        DataScope::clearCache();
    }

    private function makeUser(array $roles = [], array $perms = [], ?Team $team = null, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => $status,
            'team_id' => $team?->id,
        ]);

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->attach($role->id);
        }

        if (! empty($perms)) {
            $permissionIds = Permission::whereIn('name', $perms)->pluck('id')->all();
            $user->permissions()->sync($permissionIds);
        }

        return $user;
    }

    // =========================================================================
    // P17A: Navegación y Vistas Principales
    // =========================================================================

    public function test_authenticated_user_can_view_navigation_with_accessible_mobile_drawer(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'contacts.view', 'tasks.view', 'sales.view']);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('mobile-sidebar');
        $response->assertSee('Comercial');
        $response->assertSee('Operación');
        $response->assertSee('Ventas');
    }

    // =========================================================================
    // P17B: Kanban Táctil / Accesible
    // =========================================================================

    public function test_user_can_move_opportunity_stage_via_accessible_patch_endpoint(): void
    {
        $user = $this->makeUser(['Vendedor'], ['opportunities.view', 'opportunities.update']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stages = $pipeline->stages()->orderBy('order')->get();

        $initialStage = $stages[0];
        $targetStage = $stages[1];

        $opp = Opportunity::factory()->create([
            'owner_id' => $user->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $initialStage->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('opportunities.stage.update', $opp), [
            'pipeline_stage_id' => $targetStage->id,
        ]);

        $response->assertOk();
        $this->assertEquals($targetStage->id, $opp->fresh()->pipeline_stage_id);
    }

    public function test_kanban_view_renders_accessible_stage_selector_for_each_card(): void
    {
        $user = $this->makeUser(['Vendedor'], ['opportunities.view', 'opportunities.update']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stage = $pipeline->stages()->firstOrFail();

        $opp = Opportunity::factory()->create([
            'name' => 'Oportunidad Kanban Accesible',
            'owner_id' => $user->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
        ]);

        $response = $this->actingAs($user)->get(route('opportunities.kanban'));
        $response->assertOk();
        $response->assertSee('Oportunidad Kanban Accesible');
        $response->assertSee("stage-select-{$opp->id}");
    }

    // =========================================================================
    // P17C: Búsqueda Global Autorizada
    // =========================================================================

    public function test_global_search_returns_authorized_results_across_modules(): void
    {
        $user = $this->makeUser(['Supervisor'], [
            'companies.view', 'contacts.view', 'leads.view', 'opportunities.view', 'tickets.view',
        ]);

        $company = Company::factory()->create(['trade_name' => 'Acme Corporation', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['first_name' => 'Acme', 'last_name' => 'Contact', 'owner_id' => $user->id]);
        $lead = Lead::factory()->create(['company_name' => 'Acme Prospect', 'owner_id' => $user->id]);
        $opp = Opportunity::factory()->create(['name' => 'Acme Big Deal', 'owner_id' => $user->id]);
        $ticket = Ticket::factory()->create(['subject' => 'Acme Issue Ticket', 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('search.index', ['q' => 'Acme']));
        $response->assertOk();
        $response->assertSee('Acme Corporation');
        $response->assertSee('Acme Contact');
        $response->assertSee('Acme Prospect');
        $response->assertSee('Acme Big Deal');
        $response->assertSee('Acme Issue Ticket');
    }

    public function test_global_search_json_endpoint_provides_suggestions(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view']);
        Company::factory()->create(['trade_name' => 'Tech Innovations SAC', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->getJson(route('search.index', ['q' => 'Tech']));
        $response->assertOk();
        $response->assertJsonStructure(['query', 'total', 'items']);
        $response->assertJsonFragment(['title' => 'Tech Innovations SAC']);
    }

    public function test_global_search_enforces_datascope_isolation_between_teams(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $userA = $this->makeUser(['Vendedor'], ['companies.view'], $teamA);
        $userB = $this->makeUser(['Vendedor'], ['companies.view'], $teamB);

        // Empresa privada de teamA
        $companyA = Company::factory()->create(['trade_name' => 'Secret Company Alpha', 'owner_id' => $userA->id]);

        // userA la encuentra
        $resA = $this->actingAs($userA)->getJson(route('search.index', ['q' => 'Secret']));
        $resA->assertOk();
        $this->assertEquals(1, $resA->json('total'));

        // userB no la encuentra ni ve su conteo
        $resB = $this->actingAs($userB)->getJson(route('search.index', ['q' => 'Secret']));
        $resB->assertOk();
        $this->assertEquals(0, $resB->json('total'));
        $this->assertEmpty($resB->json('items'));
    }

    public function test_short_search_query_does_not_execute_broad_query(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view']);
        Company::factory()->create(['trade_name' => 'A Empresa', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->getJson(route('search.index', ['q' => 'a']));
        $response->assertOk();
        $this->assertEquals(0, $response->json('total'));
    }
}
