<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 9.5 — DataScope: permiso funcional + alcance de datos.
 *
 * Roles: Superadministrador/Administrador → global; Gerente/Supervisor → equipo;
 * Vendedor → propio; Soporte → regla propia en tickets; Consulta → solo lectura;
 * sin rol de alcance → propio (fallback seguro, sin acceso global implícito).
 */
class DataScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DataScope::clearCache();
    }

    private function makeTeam(string $slug): Team
    {
        return Team::create(['name' => $slug, 'slug' => $slug, 'status' => 'active']);
    }

    /**
     * @param  array<int, string>  $perms
     * @param  array<int, string>  $roles
     */
    private function makeUser(array $perms = [], ?Team $team = null, array $roles = []): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class, TicketCategorySeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
            'team_id' => $team?->id,
        ]);

        if ($perms) {
            $user->permissions()->sync(
                Permission::whereIn('name', $perms)->pluck('id')->all()
            );
        }

        foreach ($roles as $role) {
            $user->roles()->attach(
                Role::where('name', $role)->firstOrFail()->id
            );
        }

        return $user->fresh();
    }

    /** @return array<int, string> */
    private function modPerms(string ...$modules): array
    {
        $perms = [];
        foreach ($modules as $module) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $perms[] = "{$module}.{$action}";
            }
        }

        return $perms;
    }

    private function allCommercialPerms(): array
    {
        return array_merge(
            $this->modPerms('companies', 'contacts', 'leads', 'opportunities', 'quotes', 'sales', 'invoices', 'tasks', 'activities', 'tickets'),
            ['leads.convert']
        );
    }

    private function ventasPipeline(): Pipeline
    {
        return Pipeline::where('name', 'Ventas')->firstOrFail();
    }

    private function makeOpp(User $owner, ?Company $company = null): Opportunity
    {
        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();

        return Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'company_id' => $company?->id ?? Company::factory()->create(['owner_id' => $owner->id])->id,
            'owner_id' => $owner->id,
            'status' => 'open',
            'probability' => 10,
        ]);
    }

    // ---------------- Compatibilidad ----------------

    public function test_role_less_user_does_not_get_implicit_global_access(): void
    {
        $roleLess = $this->makeUser($this->modPerms('companies'));
        $other = User::factory()->create(['status' => 'active']);
        Company::factory()->create(['trade_name' => 'Mía', 'owner_id' => $roleLess->id]);
        $theirs = Company::factory()->create(['trade_name' => 'Ajena', 'owner_id' => $other->id]);

        $response = $this->actingAs($roleLess)->get('/companies');
        $response->assertOk()->assertSee('Mía')->assertDontSee('Ajena');
        $this->actingAs($roleLess)->get("/companies/{$theirs->id}")->assertForbidden();
        $this->assertSame('own', DataScope::level($roleLess));
    }

    public function test_admin_global_scope_still_requires_functional_permission(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');

        $adminWithoutPermission = $this->makeUser([], $teamA, ['Administrador']);
        $adminWithPermission = $this->makeUser(['companies.view'], $teamA, ['Administrador']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        Company::factory()->create(['trade_name' => 'Ajena Global', 'owner_id' => $vendorB->id]);

        $this->actingAs($adminWithoutPermission)->get('/companies')->assertForbidden();
        $this->actingAs($adminWithPermission)->get('/companies')->assertOk()->assertSee('Ajena Global');
    }

    // ---------------- IDOR ----------------

    public function test_idor_denied_on_show_across_modules(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('companies', 'contacts', 'leads', 'opportunities', 'quotes', 'sales', 'invoices', 'tasks', 'activities', 'tickets');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        $contactB = Contact::factory()->create(['owner_id' => $vendorB->id, 'company_id' => $companyB->id]);
        $leadB = Lead::factory()->create(['owner_id' => $vendorB->id]);
        $oppB = $this->makeOpp($vendorB, $companyB);
        $quoteB = Quote::factory()->create(['company_id' => $companyB->id, 'owner_id' => $vendorB->id]);
        $saleB = Sale::factory()->create(['company_id' => $companyB->id, 'owner_id' => $vendorB->id]);
        $invoiceB = Invoice::factory()->create(['company_id' => $companyB->id, 'owner_id' => $vendorB->id]);
        $taskB = Task::create([
            'title' => 'Ajena', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id,
        ]);
        $ticketB = Ticket::factory()->create(['assigned_to' => $vendorB->id, 'created_by' => $vendorB->id]);
        $activityB = Activity::create([
            'type' => 'note', 'subject' => 'Ajena', 'status' => 'completed',
            'user_id' => $vendorB->id, 'subjectable_type' => Company::class, 'subjectable_id' => $companyB->id,
        ]);

        $asA = fn (string $method, string $url, array $data = []) => $this->actingAs($vendorA)->{$method}($url, $data);

        $asA('get', "/companies/{$companyB->id}")->assertForbidden();
        $asA('get', "/contacts/{$contactB->id}")->assertForbidden();
        $asA('get', "/leads/{$leadB->id}")->assertForbidden();
        $asA('get', "/opportunities/{$oppB->id}")->assertForbidden();
        $asA('get', "/quotes/{$quoteB->id}")->assertForbidden();
        $asA('get', "/sales/{$saleB->id}")->assertForbidden();
        $asA('get', "/invoices/{$invoiceB->id}")->assertForbidden();
        $asA('get', "/tasks/{$taskB->id}")->assertForbidden();
        $asA('get', "/tickets/{$ticketB->id}")->assertForbidden();
        $asA('get', "/activities/{$activityB->id}")->assertForbidden();
    }

    public function test_idor_denied_on_write_actions(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = array_merge($this->modPerms('companies', 'contacts', 'leads', 'opportunities', 'quotes', 'tickets'), ['leads.convert']);

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyB = Company::factory()->create(['owner_id' => $vendorB->id, 'trade_name' => 'Ajena SA']);
        $contactB = Contact::factory()->create(['owner_id' => $vendorB->id]);
        $leadB = Lead::factory()->create(['status' => 'qualified', 'owner_id' => $vendorB->id]);
        $oppB = $this->makeOpp($vendorB, $companyB);
        $quoteB = Quote::factory()->create(['status' => 'draft', 'company_id' => $companyB->id, 'owner_id' => $vendorB->id]);
        $ticketB = Ticket::factory()->create(['status' => 'open', 'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($vendorA)->put("/companies/{$companyB->id}", ['trade_name' => 'Hack'])->assertForbidden();
        $this->actingAs($vendorA)->delete("/companies/{$companyB->id}")->assertForbidden();
        $this->actingAs($vendorA)->delete("/contacts/{$contactB->id}")->assertForbidden();
        $this->actingAs($vendorA)->patch("/leads/{$leadB->id}/qualify")->assertForbidden();
        $this->actingAs($vendorA)->post("/leads/{$leadB->id}/convert", [
            'company_mode' => 'new', 'company_name' => 'X', 'contact_mode' => 'new',
        ])->assertForbidden();
        $this->actingAs($vendorA)->patch("/opportunities/{$oppB->id}/stage", [
            'pipeline_stage_id' => $this->ventasPipeline()->stages()->orderBy('position')->skip(1)->firstOrFail()->id,
        ])->assertForbidden();
        $this->actingAs($vendorA)->patch("/quotes/{$quoteB->id}/send")->assertForbidden();
        $this->actingAs($vendorA)->post("/tickets/{$ticketB->id}/messages", [
            'type' => 'reply', 'body' => 'Hola',
        ])->assertForbidden();

        // Nada cambió ni se creó de más.
        $this->assertSame('Ajena SA', $companyB->fresh()->trade_name);
        $this->assertSame(0, Sale::count());
    }

    // ---------------- Matrices de alcance ----------------

    public function test_vendor_supervisor_admin_index_matrix(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('companies');

        $vendorA1 = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorA2 = $this->makeUser([], $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $supervisorA = $this->makeUser($perms, $teamA, ['Supervisor']);
        $admin = $this->makeUser($perms, $teamB, ['Administrador']);

        Company::factory()->create(['trade_name' => 'De A1', 'owner_id' => $vendorA1->id]);
        Company::factory()->create(['trade_name' => 'De A2', 'owner_id' => $vendorA2->id]);
        Company::factory()->create(['trade_name' => 'De B', 'owner_id' => $vendorB->id]);

        $this->actingAs($vendorA1)->get('/companies')->assertOk()
            ->assertSee('De A1')->assertDontSee('De A2')->assertDontSee('De B');

        $this->actingAs($supervisorA)->get('/companies')->assertOk()
            ->assertSee('De A1')->assertSee('De A2')->assertDontSee('De B');

        $this->actingAs($admin)->get('/companies')->assertOk()
            ->assertSee('De A1')->assertSee('De A2')->assertSee('De B');
    }

    public function test_null_team_users_gain_no_mutual_access(): void
    {
        $perms = $this->modPerms('companies', 'leads');

        $vendorNull1 = $this->makeUser($perms, null, ['Vendedor']);
        $vendorNull2 = $this->makeUser([], null, ['Vendedor']);

        Company::factory()->create(['trade_name' => 'Solo N1', 'owner_id' => $vendorNull1->id]);
        Company::factory()->create(['trade_name' => 'Solo N2', 'owner_id' => $vendorNull2->id]);
        $otherCompany = Company::where('trade_name', 'Solo N2')->firstOrFail();

        $this->actingAs($vendorNull1)->get('/companies')->assertOk()
            ->assertSee('Solo N1')->assertDontSee('Solo N2');
        $this->actingAs($vendorNull1)->get("/companies/{$otherCompany->id}")->assertForbidden();
    }

    public function test_kanban_respects_scope_in_columns_and_totals(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('opportunities');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $this->makeOpp($vendorA, null);
        $this->makeOpp($vendorA, null);
        $this->makeOpp($vendorB, null);

        $response = $this->actingAs($vendorA)->get('/opportunities/kanban');
        $response->assertOk();

        $stages = $response->viewData('stages');
        $totals = $response->viewData('totals');
        $prospecto = $stages->firstWhere('name', 'Prospecto');

        $this->assertSame(2, $prospecto->opportunities->count());
        $this->assertSame(2, $totals[$prospecto->id]['count']);
        foreach ($stages as $stage) {
            foreach ($stage->opportunities as $opp) {
                $this->assertSame($vendorA->id, $opp->owner_id);
            }
        }
    }

    public function test_dashboard_respects_scope(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = array_merge($this->modPerms('quotes', 'sales', 'invoices', 'tickets'), ['leads.convert']);

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $admin = $this->makeUser($perms, $teamB, ['Administrador']);

        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        Quote::factory()->create(['company_id' => $companyB->id, 'owner_id' => $vendorB->id, 'status' => 'draft', 'number' => 'Q-2099-000001']);
        Ticket::factory()->create(['subject' => 'Ajeno Urgente', 'priority' => 'urgent', 'status' => 'open', 'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id]);

        $companyA = Company::factory()->create(['owner_id' => $vendorA->id]);
        Quote::factory()->create(['company_id' => $companyA->id, 'owner_id' => $vendorA->id, 'status' => 'draft', 'number' => 'Q-2099-000002']);

        $this->actingAs($vendorA)->get('/dashboard')->assertOk()
            ->assertSee('Q-2099-000002')->assertDontSee('Q-2099-000001')->assertDontSee('Ajeno Urgente');

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('Q-2099-000001')->assertSee('Q-2099-000002')->assertSee('Ajeno Urgente');
    }

    // ---------------- Conversiones ----------------

    public function test_lead_convert_out_of_scope_forbidden_without_partials(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = array_merge($this->modPerms('leads', 'companies', 'contacts'), ['leads.convert']);

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $leadB = Lead::factory()->create(['status' => 'qualified', 'owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->get("/leads/{$leadB->id}/convert")->assertForbidden();
        $this->actingAs($vendorA)->post("/leads/{$leadB->id}/convert", [
            'company_mode' => 'new', 'company_name' => 'X', 'contact_mode' => 'new',
        ])->assertForbidden();

        $this->assertSame(0, Company::count());
        $this->assertSame('qualified', $leadB->fresh()->status);
    }

    public function test_quote_to_sale_out_of_scope_forbidden(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = array_merge($this->modPerms('quotes', 'sales'), ['leads.convert']);

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        $quoteB = Quote::factory()->create(['status' => 'accepted', 'company_id' => $companyB->id, 'owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->get("/quotes/{$quoteB->id}/sale/create")->assertForbidden();
        $this->actingAs($vendorA)->post("/quotes/{$quoteB->id}/sale", [])->assertForbidden();
        $this->assertSame(0, Sale::count());
    }

    public function test_sale_to_invoice_out_of_scope_forbidden(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('sales', 'invoices');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        $saleB = Sale::factory()->create(['status' => 'confirmed', 'company_id' => $companyB->id, 'owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->get("/sales/{$saleB->id}/invoice/create")->assertForbidden();
        $this->actingAs($vendorA)->post("/sales/{$saleB->id}/invoice", [])->assertForbidden();
        $this->assertSame(0, Invoice::count());
    }

    // ---------------- Fugas en relaciones ----------------

    public function test_company_show_hides_out_of_scope_relations(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('companies', 'contacts', 'opportunities', 'quotes', 'tasks');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyA = Company::factory()->create(['trade_name' => 'Propia SA', 'owner_id' => $vendorA->id]);
        $oppB = $this->makeOpp($vendorB, $companyA);
        $oppB->update(['name' => 'Oportunidad Ajena']);
        $quoteB = Quote::factory()->create(['company_id' => $companyA->id, 'owner_id' => $vendorB->id, 'number' => 'Q-2099-000003']);
        $taskB = Task::create([
            'title' => 'Tarea Ajena', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id,
            'taskable_type' => Company::class, 'taskable_id' => $companyA->id,
        ]);

        $this->actingAs($vendorA)->get("/companies/{$companyA->id}")->assertOk()
            ->assertDontSee('Oportunidad Ajena')
            ->assertDontSee('Q-2099-000003')
            ->assertDontSee('Tarea Ajena');
    }

    public function test_opportunity_show_hides_out_of_scope_links(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('opportunities', 'companies');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyB = Company::factory()->create(['trade_name' => 'Ajena Oculta SA', 'owner_id' => $vendorB->id]);
        $oppA = $this->makeOpp($vendorA, $companyB);

        $this->actingAs($vendorA)->get("/opportunities/{$oppA->id}")->assertOk()
            ->assertDontSee('Ajena Oculta SA');
    }

    public function test_forms_do_not_prefill_or_list_out_of_scope_related_records(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('contacts', 'tasks', 'activities', 'quotes', 'opportunities');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyA = Company::factory()->create(['trade_name' => 'Visible Form', 'owner_id' => $vendorA->id]);
        $companyB = Company::factory()->create(['trade_name' => 'Oculta Form', 'owner_id' => $vendorB->id]);
        $oppB = $this->makeOpp($vendorB, $companyB);
        $oppB->update(['name' => 'Prefill Ajeno']);

        $contactCreate = $this->actingAs($vendorA)->get("/contacts/create?company_id={$companyB->id}");
        $contactCreate->assertOk();
        $this->assertNull($contactCreate->viewData('preselectedCompanyId'));
        $this->assertContains($companyA->id, $contactCreate->viewData('companies')->pluck('id')->all());
        $this->assertNotContains($companyB->id, $contactCreate->viewData('companies')->pluck('id')->all());

        $taskCreate = $this->actingAs($vendorA)->get("/tasks/create?related=company:{$companyB->id}");
        $taskCreate->assertOk();
        $this->assertSame(['type' => null, 'id' => null, 'label' => null], $taskCreate->viewData('preselected'));

        $activityCreate = $this->actingAs($vendorA)->get("/activities/create?related=company:{$companyB->id}");
        $activityCreate->assertOk();
        $this->assertSame(['type' => null, 'id' => null, 'label' => null], $activityCreate->viewData('preselected'));

        $quoteCreate = $this->actingAs($vendorA)->get("/quotes/create?opportunity_id={$oppB->id}");
        $quoteCreate->assertOk();
        $this->assertSame([], $quoteCreate->viewData('prefill'));
        $this->assertNotContains($oppB->id, $quoteCreate->viewData('opportunities')->pluck('id')->all());
    }

    public function test_direct_writes_reject_out_of_scope_related_ids_and_owner_ids(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = array_merge($this->modPerms('companies', 'contacts', 'quotes', 'leads'), ['leads.convert']);

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $companyB = Company::factory()->create(['trade_name' => 'Empresa Ajena Directa', 'owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->post('/companies', [
            'trade_name' => 'Reasignada',
            'status' => 'active',
            'owner_id' => $vendorB->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('companies', ['trade_name' => 'Reasignada']);

        $this->actingAs($vendorA)->post('/contacts', [
            'first_name' => 'Contacto',
            'status' => 'active',
            'company_id' => $companyB->id,
            'owner_id' => $vendorA->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('contacts', ['first_name' => 'Contacto']);

        $this->actingAs($vendorA)->post('/quotes', [
            'company_id' => $companyB->id,
            'owner_id' => $vendorA->id,
            'currency' => 'USD',
            'items' => [[
                'description' => 'Servicio',
                'unit' => 'unit',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertForbidden();
        $this->assertSame(0, Quote::count());

        $leadA = Lead::factory()->create(['status' => 'qualified', 'owner_id' => $vendorA->id]);
        $this->actingAs($vendorA)->post("/leads/{$leadA->id}/convert", [
            'company_mode' => 'existing',
            'company_id' => $companyB->id,
            'contact_mode' => 'new',
        ])->assertForbidden();
        $this->assertSame('qualified', $leadA->fresh()->status);
    }

    public function test_indexes_hide_out_of_scope_relation_names_for_historical_rows(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('contacts', 'quotes');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyB = Company::factory()->create(['trade_name' => 'Empresa Oculta Histórica', 'owner_id' => $vendorB->id]);
        Contact::factory()->create([
            'first_name' => 'Contacto Histórico',
            'company_id' => $companyB->id,
            'owner_id' => $vendorA->id,
        ]);
        Quote::factory()->create([
            'number' => 'Q-2099-000099',
            'company_id' => $companyB->id,
            'owner_id' => $vendorA->id,
        ]);

        $this->actingAs($vendorA)->get('/contacts')->assertOk()
            ->assertSee('Contacto Histórico')
            ->assertDontSee('Empresa Oculta Histórica');

        $this->actingAs($vendorA)->get('/quotes')->assertOk()
            ->assertSee('Q-2099-000099')
            ->assertDontSee('Empresa Oculta Histórica');
    }

    // ---------------- Tickets por rol ----------------

    public function test_support_ticket_visibility_matrix(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('tickets');

        $supportA = $this->makeUser($perms, $teamA, ['Soporte']);
        $supportB = $this->makeUser([], $teamB, ['Soporte']);
        $supervisorA = $this->makeUser($perms, $teamA, ['Supervisor']);
        $vendorB = $this->makeUser($perms, $teamB, ['Vendedor']);

        $mine = Ticket::factory()->create(['subject' => 'Mío Soporte', 'assigned_to' => $supportA->id, 'created_by' => $supportA->id]);
        $queue = Ticket::factory()->create(['subject' => 'Cola General', 'assigned_to' => null, 'created_by' => $supportA->id]);
        $theirs = Ticket::factory()->create(['subject' => 'De Otro Agente', 'assigned_to' => $supportB->id, 'created_by' => $supportB->id]);
        $ownB = Ticket::factory()->create(['subject' => 'Propio B', 'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id]);

        // Soporte: propios + sin asignar, nunca los de otro agente.
        $this->actingAs($supportA)->get('/tickets')->assertOk()
            ->assertSee('Mío Soporte')->assertSee('Cola General')->assertDontSee('De Otro Agente');
        $this->actingAs($supportA)->get("/tickets/{$theirs->id}")->assertForbidden();
        $this->actingAs($supportA)->get("/tickets/{$mine->id}")->assertOk();
        $this->actingAs($supportA)->get("/tickets/{$queue->id}")->assertOk();

        // Supervisor del equipo: ve los del equipo + cola.
        $this->actingAs($supervisorA)->get('/tickets')->assertOk()
            ->assertSee('Mío Soporte')->assertSee('Cola General')->assertDontSee('De Otro Agente');

        // Otro equipo, nivel propio: solo lo suyo.
        $this->actingAs($vendorB)->get('/tickets')->assertOk()
            ->assertDontSee('Mío Soporte')->assertDontSee('Cola General')
            ->assertDontSee('De Otro Agente')->assertSee('Propio B');
    }

    // ---------------- Consulta solo lectura ----------------

    public function test_consulta_is_read_only_even_with_write_permissions(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        // Simula matriz inconsistente: permisos de escritura asignados por error.
        $perms = ['companies.view', 'companies.create', 'companies.update', 'companies.delete'];

        $consulta = $this->makeUser($perms, $teamA, ['Consulta']);
        $own = Company::factory()->create(['trade_name' => 'Propia Consulta', 'owner_id' => $consulta->id]);

        $this->actingAs($consulta)->get('/companies')->assertOk()->assertSee('Propia Consulta');
        $this->actingAs($consulta)->get("/companies/{$own->id}")->assertOk();
        $this->actingAs($consulta)->post('/companies', [
            'trade_name' => 'No Debe', 'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($consulta)->put("/companies/{$own->id}", [
            'trade_name' => 'Cambiada', 'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($consulta)->delete("/companies/{$own->id}")->assertForbidden();
        $this->assertSame('Propia Consulta', $own->fresh()->trade_name);
    }

    // ---------------- Actividades y tareas ----------------

    public function test_activity_index_and_show_respect_scope(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('companies', 'activities');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyA = Company::factory()->create(['owner_id' => $vendorA->id]);
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);

        // Autoría del admin sobre empresa propia: visible por entidad.
        $admin = User::factory()->create(['status' => 'active']);
        $visible = $companyA->activities()->create([
            'type' => 'note', 'subject' => 'Nota Visible', 'status' => 'completed', 'user_id' => $admin->id,
        ]);
        $hidden = $companyB->activities()->create([
            'type' => 'note', 'subject' => 'Nota Oculta', 'status' => 'completed', 'user_id' => $vendorB->id,
        ]);

        $this->actingAs($vendorA)->get('/activities')->assertOk()
            ->assertSee('Nota Visible')->assertDontSee('Nota Oculta');
        $this->actingAs($vendorA)->get("/activities/{$visible->id}")->assertOk();
        $this->actingAs($vendorA)->get("/activities/{$hidden->id}")->assertForbidden();
    }

    public function test_task_scope_assigned_or_created(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('tasks');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $mate = $this->makeUser([], $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $supervisorA = $this->makeUser($perms, $teamA, ['Supervisor']);

        $assigned = Task::create([
            'title' => 'Asignada A', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $vendorA->id, 'created_by' => $mate->id,
        ]);
        $created = Task::create([
            'title' => 'Creada Por A', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $mate->id, 'created_by' => $vendorA->id,
        ]);
        $foreign = Task::create([
            'title' => 'Ajena B', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $vendorB->id, 'created_by' => $vendorB->id,
        ]);

        $this->actingAs($vendorA)->get('/tasks')->assertOk()
            ->assertSee('Asignada A')->assertSee('Creada Por A')->assertDontSee('Ajena B');
        $this->actingAs($vendorA)->get("/tasks/{$foreign->id}")->assertForbidden();

        $this->actingAs($supervisorA)->get('/tasks')->assertOk()
            ->assertSee('Asignada A')->assertSee('Creada Por A')->assertDontSee('Ajena B');
    }

    public function test_task_creation_on_out_of_scope_entity_forbidden(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('tasks', 'activities');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->post('/tasks', [
            'title' => 'Intrusa', 'status' => 'pending', 'priority' => 'medium',
            'related_type' => 'company', 'related_id' => $companyB->id,
        ])->assertForbidden();
        $this->assertSame(0, Task::count());

        $this->actingAs($vendorA)->post('/activities', [
            'type' => 'note', 'subject' => 'Intrusa', 'status' => 'pending',
            'related_type' => 'company', 'related_id' => $companyB->id,
        ])->assertForbidden();
        $this->assertSame(0, Activity::count());
    }

    // ---------------- Filtros por responsable ----------------

    public function test_owner_filter_options_follow_scope(): void
    {
        $teamA = $this->makeTeam('equipo-a');
        $teamB = $this->makeTeam('equipo-b');
        $perms = $this->modPerms('companies');

        $vendorA = $this->makeUser($perms, $teamA, ['Vendedor']);
        $mateA = $this->makeUser([], $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $supervisorA = $this->makeUser($perms, $teamA, ['Supervisor']);

        $ownersVendor = $this->actingAs($vendorA)->get('/companies')->viewData('owners');
        $this->assertSame([$vendorA->id], $ownersVendor->pluck('id')->all());

        $ownersSup = $this->actingAs($supervisorA)->get('/companies')->viewData('owners');
        $this->assertEqualsCanonicalizing(
            [$vendorA->id, $mateA->id, $supervisorA->id],
            $ownersSup->pluck('id')->all()
        );
        $this->assertNotContains($vendorB->id, $ownersSup->pluck('id')->all());
    }
}
