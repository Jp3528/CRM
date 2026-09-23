<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Reports\ForecastService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\SalesReportService;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 12 — Dashboard ejecutivo + reportes + forecast (DataScope primero,
 * montos por moneda, sin FX, sin IA).
 */
class PhaseTwelveTest extends TestCase
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

    /** @param array<int,string> $perms @param array<int,string> $roles */
    private function makeUser(array $perms = [], ?Team $team = null, array $roles = []): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class, TicketCategorySeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
            'team_id' => $team?->id,
        ]);

        if ($perms) {
            $user->permissions()->sync(Permission::whereIn('name', $perms)->pluck('id')->all());
        }

        foreach ($roles as $role) {
            $user->roles()->attach(\App\Models\Role::where('name', $role)->firstOrFail()->id);
        }

        return $user->fresh();
    }

    /** @return array<int,string> */
    private function reportPerms(): array
    {
        return [
            'reports.view', 'reports.forecast',
            'opportunities.view', 'sales.view', 'invoices.view', 'leads.view',
            'quotes.view', 'tickets.view', 'campaigns.view', 'communications.view',
            'automations.view', 'tasks.view',
        ];
    }

    private function filters(User $user, array $overrides = []): ReportFilters
    {
        return ReportFilters::resolve(array_merge([
            'date_from' => '2026-09-01', 'date_to' => '2026-09-30',
        ], $overrides), $user);
    }

    private function ventasPipeline(): Pipeline
    {
        return Pipeline::where('name', 'Ventas')->firstOrFail();
    }

    // ---------------- permisos ----------------

    public function test_reports_require_permissions(): void
    {
        $noReports = $this->makeUser(['sales.view'], null, ['Vendedor']);
        $this->actingAs($noReports)->get('/reports')->assertForbidden();
        $this->actingAs($noReports)->get('/reports/sales')->assertForbidden();

        $noModule = $this->makeUser(['reports.view'], null, ['Vendedor']);
        $this->actingAs($noModule)->get('/reports')->assertOk();
        $this->actingAs($noModule)->get('/reports/sales')->assertForbidden();

        $noForecast = $this->makeUser(['reports.view', 'opportunities.view'], null, ['Vendedor']);
        $this->actingAs($noForecast)->get('/forecast')->assertForbidden();
    }

    public function test_report_center_cards_follow_module_permissions(): void
    {
        $user = $this->makeUser(['reports.view', 'sales.view'], null, ['Vendedor']);
        $this->actingAs($user)->get('/reports')->assertOk()
            ->assertSee('Ventas')->assertDontSee('Pipeline');
    }

    // ---------------- scope ----------------

    public function test_vendor_sees_only_own_sales(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyA = Company::factory()->create(['owner_id' => $vendorA->id]);
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        Sale::factory()->create(['number' => 'S-2026-000101', 'company_id' => $companyA->id, 'owner_id' => $vendorA->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 1000, 'sale_date' => '2026-09-10']);
        Sale::factory()->create(['number' => 'S-2026-000102', 'company_id' => $companyB->id, 'owner_id' => $vendorB->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 5000, 'sale_date' => '2026-09-11']);

        $response = $this->actingAs($vendorA)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30');
        $response->assertOk()
            ->assertSee('1,000.00 USD')
            ->assertDontSee('5,000.00 USD')
            ->assertDontSee('S-2026-000102');
    }

    public function test_supervisor_sees_team_and_admin_global(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser([], $teamA, ['Vendedor']);
        $mate = $this->makeUser([], $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $supervisorA = $this->makeUser($this->reportPerms(), $teamA, ['Supervisor']);
        $admin = $this->makeUser($this->reportPerms(), $teamB, ['Administrador']);

        foreach ([$vendorA, $mate, $vendorB] as $i => $owner) {
            $company = Company::factory()->create(['owner_id' => $owner->id]);
            Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $owner->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => [100, 150, 9999][$i], 'sale_date' => '2026-09-10']);
        }

        $this->actingAs($supervisorA)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('100.00 USD')->assertSee('150.00 USD')->assertDontSee('9,999.00 USD');

        $this->actingAs($admin)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('100.00 USD')->assertSee('150.00 USD')->assertSee('9,999.00 USD');
    }

    public function test_role_less_user_is_not_global(): void
    {
        $roleLess = $this->makeUser(['reports.view', 'sales.view']);
        $other = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create(['owner_id' => $other->id]);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $other->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 777, 'sale_date' => '2026-09-10']);

        $this->actingAs($roleLess)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertDontSee('777.00 USD');
    }

    public function test_consulta_read_only_scoped(): void
    {
        $consulta = $this->makeUser(['reports.view', 'reports.forecast', 'leads.view', 'opportunities.view'], null, ['Consulta']);
        Lead::factory()->create(['owner_id' => $consulta->id, 'created_at' => '2026-09-05 10:00:00', 'updated_at' => '2026-09-05 10:00:00']);
        Lead::factory()->create(['owner_id' => $consulta->id, 'created_at' => '2026-09-06 10:00:00', 'updated_at' => '2026-09-06 10:00:00']);

        $this->actingAs($consulta)->get('/reports')->assertOk();
        $response = $this->actingAs($consulta)->get('/reports/leads?date_from=2026-09-01&date_to=2026-09-30');
        $response->assertOk();
        $this->assertSame(2, $response->viewData('summary')['created']);
        // Reportes y forecast son solo lectura: no existen rutas de escritura.
        $writeRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'reports') || str_starts_with($r->uri(), 'forecast'))
            ->flatMap(fn ($r) => $r->methods())
            ->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $writeRoutes);
    }

    // ---------------- owner filter IDOR ----------------

    public function test_owner_filter_out_of_scope_rejected(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $this->actingAs($vendorA)->get("/reports/sales?owner_id={$vendorB->id}")
            ->assertSessionHasErrors('owner_id');
        $this->actingAs($vendorA)->get('/forecast?owner_id='.$vendorB->id)
            ->assertSessionHasErrors('owner_id');
    }

    // ---------------- date ranges ----------------

    public function test_date_range_filters_and_previous_period(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 200, 'sale_date' => '2026-09-15']);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 100, 'sale_date' => '2026-08-20']);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 999, 'sale_date' => '2025-01-01']);

        // Septiembre: 200 actual vs 100 previo (21–31 ago) → +100.0%.
        $this->actingAs($user)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('200.00 USD')->assertSee('+100.0%')->assertDontSee('999.00 USD');
    }

    public function test_invalid_range_rejected(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);

        $this->actingAs($user)->get('/reports/sales?date_from=2026-03-01&date_to=2026-01-01')
            ->assertSessionHasErrors('date_to');
        $this->actingAs($user)->get('/reports/sales?date_from=2010-01-01&date_to=2026-09-01')
            ->assertSessionHasErrors('date_to');
    }

    // ---------------- multi-currency ----------------

    public function test_multi_currency_never_mixed(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 1000, 'sale_date' => '2026-09-10']);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'COP', 'total' => 2000000, 'sale_date' => '2026-09-10']);

        $service = new SalesReportService($user, $this->filters($user));
        $summary = $service->summary();
        $this->assertArrayHasKey('USD', $summary['by_currency']);
        $this->assertArrayHasKey('COP', $summary['by_currency']);
        $this->assertEqualsWithDelta(1000.0, (float) $summary['by_currency']['USD']['total'], 0.001);
        $this->assertEqualsWithDelta(2000000.0, (float) $summary['by_currency']['COP']['total'], 0.001);

        $this->actingAs($user)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('1,000.00 USD')->assertSee('2,000,000.00 COP');
    }

    public function test_forecast_multi_currency_separate(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        foreach (['USD', 'EUR'] as $currency) {
            Opportunity::factory()->create([
                'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
                'company_id' => $company->id, 'owner_id' => $user->id,
                'status' => 'open', 'amount' => 10000, 'probability' => 50,
                'currency' => $currency, 'expected_close_date' => '2026-10-15',
            ]);
        }

        $service = new ForecastService($user, $this->filters($user, ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']));
        $forecast = $service->build();

        $totals = collect($forecast['totals'])->keyBy('currency');
        $this->assertEqualsWithDelta(5000.0, (float) $totals['USD']['weighted'], 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $totals['EUR']['weighted'], 0.001);

        $this->actingAs($user)->get('/forecast?date_from=2026-10-01&date_to=2026-10-31')->assertOk()
            ->assertSee('5,000.00 USD')->assertSee('5,000.00 EUR');
    }

    // ---------------- pipeline ----------------

    public function test_pipeline_open_only_and_weighted_exact(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        Opportunity::factory()->create([
            'name' => 'Abierta ponderada', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'open', 'amount' => 10000, 'probability' => 60, 'currency' => 'USD',
        ]);
        Opportunity::factory()->create([
            'name' => 'Ganada excluida', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'won', 'amount' => 50000, 'probability' => 100, 'currency' => 'USD',
        ]);

        $this->actingAs($user)->get('/reports/pipeline')->assertOk()
            ->assertSee('6,000.00 USD')
            ->assertDontSee('50,000.00 USD')
            ->assertDontSee('Ganada excluida');
    }

    public function test_stale_and_close_buckets(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $stale = Opportunity::factory()->create([
            'name' => 'Estancada visible', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'open',
            'expected_close_date' => null,
        ]);
        Opportunity::whereKey($stale->id)->update(['updated_at' => now()->subDays(45)]);

        $this->actingAs($user)->get('/reports/pipeline?stale_days=30')->assertOk()
            ->assertSee('Estancada visible')
            ->assertSee('Sin fecha');
    }

    // ---------------- forecast ----------------

    public function test_forecast_open_only_and_missing_date_separate(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        Opportunity::factory()->create([
            'name' => 'Cierre octubre', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'open', 'amount' => 10000, 'probability' => 60, 'currency' => 'USD',
            'expected_close_date' => '2026-10-15',
        ]);
        Opportunity::factory()->create([
            'name' => 'Sin fecha calidad', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'open', 'amount' => 7000, 'probability' => 60, 'currency' => 'USD',
            'expected_close_date' => null,
        ]);

        $response = $this->actingAs($user)->get('/forecast?date_from=2026-10-01&date_to=2026-12-31');
        $response->assertOk()
            ->assertSee('2026-10')
            ->assertSee('6,000.00 USD')
            ->assertSee('Sin fecha estimada');
    }

    public function test_forecast_requires_permission(): void
    {
        $user = $this->makeUser(['opportunities.view'], null, ['Vendedor']);
        $this->actingAs($user)->get('/forecast')->assertForbidden();
    }

    // ---------------- leads ----------------

    public function test_lead_funnel_and_conversion_rate(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);

        foreach (['new', 'contacted', 'qualified', 'unqualified'] as $status) {
            Lead::factory()->create(['status' => $status, 'owner_id' => $user->id, 'created_at' => '2026-09-05 10:00:00', 'updated_at' => '2026-09-05 10:00:00']);
        }
        Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id, 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00']);

        $response = $this->actingAs($user)->get('/reports/leads?date_from=2026-09-01&date_to=2026-09-30');
        $response->assertOk();
        // 4 creados, 0 convertidos → 0.0%.
        $response->assertSee('0.0%');

        Lead::factory()->create(['status' => 'converted', 'owner_id' => $user->id, 'converted_at' => '2026-09-10 10:00:00', 'created_at' => '2026-09-02 10:00:00', 'updated_at' => '2026-09-10 10:00:00']);

        $this->actingAs($user)->get('/reports/leads?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('20.0%');
    }

    public function test_zero_denominators_show_na(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);

        $this->actingAs($user)->get('/reports/leads?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('N/A');
        $this->actingAs($user)->get('/reports/quotes?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('N/A');
        $this->actingAs($user)->get('/reports/automations?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('N/A');
    }

    // ---------------- quotes ----------------

    public function test_quote_acceptance_definition(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        Quote::factory(2)->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'accepted', 'currency' => 'USD', 'total' => 500, 'issue_date' => '2026-09-05']);
        Quote::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'rejected', 'currency' => 'USD', 'total' => 100, 'issue_date' => '2026-09-06']);
        Quote::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'sent', 'currency' => 'USD', 'total' => 100, 'issue_date' => '2026-09-07']);

        // 2/(2+1) = 66.7% (enviada sin respuesta no decide).
        $this->actingAs($user)->get('/reports/quotes?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('66.7%')
            ->assertSee('1,000.00 USD');
    }

    // ---------------- invoices ----------------

    public function test_invoice_outstanding_and_overdue(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'draft', 'currency' => 'USD', 'total' => 100, 'issue_date' => '2026-09-05']);
        Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'sent', 'currency' => 'USD', 'total' => 200, 'issue_date' => '2026-09-06', 'due_date' => '2026-08-01']);
        Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'paid', 'currency' => 'USD', 'total' => 300, 'issue_date' => '2026-09-07', 'paid_at' => '2026-09-08 10:00:00']);
        Invoice::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'cancelled', 'currency' => 'USD', 'total' => 400, 'issue_date' => '2026-09-08']);

        $response = $this->actingAs($user)->get('/reports/invoices?date_from=2026-09-01&date_to=2026-09-30');
        $response->assertOk()
            ->assertSee('Facturación interna')
            ->assertSee('300.00 USD'); // pendiente = 100 + 200
    }

    // ---------------- support ----------------

    public function test_support_averages_exclude_nulls(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);

        Ticket::factory()->create([
            'subject' => 'Con tiempos', 'assigned_to' => $user->id, 'created_by' => $user->id,
            'status' => 'resolved', 'created_at' => '2026-09-05 10:00:00',
            'first_response_at' => '2026-09-05 11:00:00', 'resolved_at' => '2026-09-05 12:00:00',
        ]);
        Ticket::factory()->create([
            'subject' => 'Sin tiempos', 'assigned_to' => $user->id, 'created_by' => $user->id,
            'status' => 'open', 'created_at' => '2026-09-06 10:00:00',
        ]);

        $this->actingAs($user)->get('/reports/support?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('1h') // primera respuesta media
            ->assertSee('2h'); // resolución media
    }

    // ---------------- campaigns ----------------

    public function test_campaign_report_has_no_fake_metrics_and_scoped_members(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $campaign = Campaign::factory()->create(['owner_id' => $vendorA->id, 'created_by' => $vendorA->id, 'status' => 'active']);
        $mine = Contact::factory()->create(['owner_id' => $vendorA->id]);
        $foreign = Contact::factory()->create(['owner_id' => $vendorB->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $mine->id, 'added_by' => $vendorA->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $foreign->id, 'added_by' => $vendorA->id]);

        $response = $this->actingAs($vendorA)->get('/reports/campaigns');
        $response->assertOk();
        // Etiquetado honesto: se declara que NO hay métricas de proveedor.
        $response->assertSee('no hay proveedores reales');
        // 1 miembro visible (solo objetivo en alcance).
        $this->assertSame(1, $response->viewData('summary')['visible_members']);
    }

    // ---------------- automations ----------------

    public function test_automation_ratio_excludes_skipped_and_scope(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $mine = Automation::factory()->create(['name' => 'Mía auto', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id, 'status' => 'active']);
        $foreign = Automation::factory()->create(['name' => 'Ajena auto', 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id, 'status' => 'active']);

        AutomationRun::factory()->create(['automation_id' => $mine->id, 'status' => 'success', 'created_at' => '2026-09-05 10:00:00']);
        AutomationRun::factory()->create(['automation_id' => $mine->id, 'status' => 'failed', 'created_at' => '2026-09-06 10:00:00']);
        AutomationRun::factory()->create(['automation_id' => $mine->id, 'status' => 'skipped', 'created_at' => '2026-09-07 10:00:00']);
        AutomationRun::factory()->create(['automation_id' => $foreign->id, 'status' => 'failed', 'created_at' => '2026-09-08 10:00:00']);

        // 1/(1+1) = 50.0%; la ajena no cuenta.
        $this->actingAs($vendorA)->get('/reports/automations?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('50.0%')
            ->assertDontSee('Ajena auto');
    }

    // ---------------- aggregate leak ----------------

    public function test_distinctive_amount_never_leaks(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $companyB = Company::factory()->create(['trade_name' => 'Oculta SA', 'owner_id' => $vendorB->id]);
        Sale::factory()->create(['company_id' => $companyB->id, 'owner_id' => $vendorB->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 99999999.99, 'sale_date' => '2026-09-10']);

        foreach (['/dashboard', '/reports/sales?date_from=2026-09-01&date_to=2026-09-30', '/forecast?date_from=2026-09-01&date_to=2026-12-31', '/reports/pipeline'] as $url) {
            $this->actingAs($vendorA)->get($url)->assertOk()
                ->assertDontSee('99,999,999.99')
                ->assertDontSee('Oculta SA');
        }
    }

    public function test_drill_down_links_do_not_expand_scope(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->reportPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $pipeline = $this->ventasPipeline();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $companyB = Company::factory()->create(['owner_id' => $vendorB->id]);
        Opportunity::factory()->create([
            'name' => 'Ajena drill', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $companyB->id, 'owner_id' => $vendorB->id, 'status' => 'won',
            'actual_close_date' => '2026-09-12',
        ]);

        // Enlace del dashboard hacia won solo muestra lo propio.
        $this->actingAs($vendorA)->get('/opportunities?status=won')->assertOk()
            ->assertDontSee('Ajena drill');
    }

    // ---------------- XSS ----------------

    public function test_malicious_labels_are_escaped(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['trade_name' => '<script>alert(1)</script>', 'owner_id' => $user->id]);
        Sale::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id, 'status' => 'confirmed', 'currency' => 'USD', 'total' => 10, 'sale_date' => '2026-09-10']);

        $this->actingAs($user)->get('/reports/sales?date_from=2026-09-01&date_to=2026-09-30')->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---------------- dashboard compat ----------------

    public function test_dashboard_keeps_widgets_and_adds_executive(): void
    {
        $user = $this->makeUser($this->reportPerms(), null, ['Vendedor']);
        Task::create(['title' => 'Propia hoy', 'status' => 'pending', 'priority' => 'medium', 'assigned_to' => $user->id, 'created_by' => $user->id, 'due_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Propia hoy')
            ->assertSee('Resumen ejecutivo')
            ->assertSee('Pipeline abierto');
    }
}
