<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Company;
use App\Models\Contact;
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
use App\Observers\AutomationObserver;
use App\Services\Automations\AutomationTriggerDispatcher;
use App\Services\Opportunities\OpportunityStageService;
use App\Services\Tickets\TicketStatusService;
use App\Support\AutomationCatalog;
use App\Support\ConditionEvaluator;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 11 — Motor de automatizaciones internas (sin código arbitrario,
 * sin envíos, after-commit, con DataScope + RBAC revalidados al ejecutar).
 */
class PhaseElevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DataScope::clearCache();
        AutomationObserver::reset();
        AutomationTriggerDispatcher::enableSync();
    }

    protected function tearDown(): void
    {
        AutomationTriggerDispatcher::disableSync();
        AutomationObserver::reset();
        parent::tearDown();
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
    private function autoPerms(): array
    {
        return [
            'automations.view', 'automations.create', 'automations.update',
            'automations.delete', 'automations.execute',
        ];
    }

    /** @return array<int,string> */
    private function fullPerms(): array
    {
        return array_merge(
            $this->autoPerms(),
            ['tasks.view', 'tasks.create', 'tasks.update', 'activities.view', 'activities.create',
                'leads.view', 'leads.create', 'leads.update', 'contacts.view', 'contacts.create',
                'campaigns.view', 'campaigns.update', 'opportunities.view', 'opportunities.update',
                'tickets.view', 'tickets.update', 'quotes.view', 'quotes.update',
                'sales.view', 'sales.update', 'invoices.view', 'invoices.update']
        );
    }

    /** @param array<string,mixed> $overrides */
    private function automationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Seguimiento web',
            'description' => 'Regla interna.',
            'trigger_type' => 'lead.created',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'create_task',
                    'title' => 'Llamar a prospecto',
                    'priority' => 'high',
                    'due_in_days' => 2,
                    'assigned_to_mode' => 'subject_owner',
                ],
            ],
        ], $overrides);
    }

    private function makeAutomation(User $owner, array $overrides = []): Automation
    {
        return Automation::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
            'status' => 'active',
            'trigger_type' => 'lead.created',
            'conditions' => [],
            'actions' => [
                [
                    'type' => 'create_task',
                    'title' => 'Seguimiento',
                    'description' => null,
                    'priority' => 'medium',
                    'due_in_days' => 3,
                    'assigned_to_mode' => 'subject_owner',
                    'fixed_user_id' => null,
                ],
            ],
        ], $overrides));
    }

    // ---------------- CRUD ----------------

    public function test_index_requires_permission(): void
    {
        $user = $this->makeUser([], null, ['Vendedor']);
        $this->actingAs($user)->get('/automations')->assertForbidden();
    }

    public function test_store_creates_draft_and_ignores_created_by(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $other = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'owner_id' => $user->id,
            'created_by' => $other->id,
        ]))->assertRedirect();

        $automation = Automation::firstOrFail();
        $this->assertSame('draft', $automation->status);
        $this->assertSame($user->id, $automation->created_by);
        $this->assertSame($user->id, $automation->owner_id);
    }

    public function test_store_rejects_invalid_schema(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);

        // Trigger inválido.
        $this->actingAs($user)->post('/automations', $this->automationPayload(['trigger_type' => 'lead.explode']))
            ->assertSessionHasErrors('trigger_type');

        // Campo traversal.
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'conditions' => [['field' => 'owner.password', 'operator' => 'equals', 'value' => 'x']],
        ]))->assertSessionHasErrors('conditions.0.field');

        // Acción arbitraria.
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'actions' => [['type' => 'php_eval']],
        ]))->assertSessionHasErrors('actions.0.type');

        // Sin acciones.
        $this->actingAs($user)->post('/automations', $this->automationPayload(['actions' => []]))
            ->assertSessionHasErrors('actions');

        $this->assertSame(0, Automation::count());
    }

    public function test_search_filters_sorting_pagination(): void
    {
        $user = $this->makeUser($this->autoPerms(), null, ['Vendedor']);
        Automation::factory()->create(['name' => 'Alpha web', 'trigger_type' => 'lead.created', 'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'draft']);
        Automation::factory()->create(['name' => 'Beta ticket', 'trigger_type' => 'ticket.status_changed', 'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'paused']);

        $this->actingAs($user)->get('/automations?search=alpha')->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
        $this->actingAs($user)->get('/automations?status=paused')->assertOk()->assertSee('Beta')->assertDontSee('Alpha');
        $this->actingAs($user)->get('/automations?trigger_type=lead.created')->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
    }

    public function test_update_active_rejected_must_pause_first(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);
        $original = $automation->name;

        $this->actingAs($user)->put("/automations/{$automation->id}", $this->automationPayload(['name' => 'Cambio']))
            ->assertSessionHasErrors('status');
        $this->assertSame($original, $automation->fresh()->name);

        // Status por input libre también prohibido.
        $automation->update(['status' => 'paused']);
        $this->actingAs($user)->put("/automations/{$automation->id}", array_merge(
            $this->automationPayload(['name' => 'Cambio']), ['status' => 'active']
        ))->assertSessionHasErrors('status');
    }

    public function test_delete_active_rejected_paused_soft_deleted(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);

        $this->actingAs($user)->delete("/automations/{$automation->id}")->assertSessionHas('error');
        $this->assertFalse($automation->fresh()->trashed());

        $automation->update(['status' => 'paused']);
        $this->actingAs($user)->delete("/automations/{$automation->id}")->assertRedirect();
        $this->assertSoftDeleted('automations', ['id' => $automation->id]);
    }

    // ---------------- activate / pause ----------------

    public function test_activate_and_pause(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = Automation::factory()->create([
            'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'draft',
            'trigger_type' => 'lead.created', 'conditions' => [],
            'actions' => [['type' => 'create_task', 'title' => 'X', 'description' => null, 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null]],
        ]);

        $this->actingAs($user)->post("/automations/{$automation->id}/activate")->assertRedirect();
        $this->assertSame('active', $automation->fresh()->status);

        $this->actingAs($user)->post("/automations/{$automation->id}/pause")->assertRedirect();
        $this->assertSame('paused', $automation->fresh()->status);
    }

    public function test_activate_rejected_when_owner_inactive(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = Automation::factory()->create([
            'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'draft',
        ]);

        $user->update(['status' => 'inactive']);

        $admin = $this->makeUser($this->autoPerms(), null, ['Administrador']);
        // El admin ve global pero el owner está inactivo: no activa.
        $automation->update(['owner_id' => $user->id]);
        $this->actingAs($admin)->post("/automations/{$automation->id}/activate")
            ->assertSessionHas('error');
        $this->assertSame('draft', $automation->fresh()->status);
    }

    // ---------------- success + conditions ----------------

    public function test_lead_created_matching_conditions_creates_task_once(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, [
            'trigger_type' => 'lead.created',
            'conditions' => [
                ['field' => 'source', 'operator' => 'equals', 'value' => 'website'],
                ['field' => 'score', 'operator' => 'greater_or_equal', 'value' => 70],
            ],
            'actions' => [
                ['type' => 'create_task', 'title' => 'Llamar {{subject_name}}', 'description' => null, 'priority' => 'high', 'due_in_days' => 2, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null],
            ],
        ]);

        Lead::factory()->create(['source' => 'website', 'score' => 80, 'owner_id' => $user->id, 'first_name' => 'Ana', 'last_name' => 'Ruiz']);

        $this->assertSame(1, Task::count());
        $task = Task::firstOrFail();
        $this->assertSame('Llamar Ana Ruiz', $task->title);
        $this->assertSame('high', $task->priority);
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame(Lead::class, $task->taskable_type);

        $run = AutomationRun::firstOrFail();
        $this->assertSame('success', $run->status);
        $this->assertSame('lead.created', $run->trigger_type);
    }

    public function test_conditions_false_skips_without_actions(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, [
            'conditions' => [['field' => 'source', 'operator' => 'equals', 'value' => 'website']],
        ]);

        Lead::factory()->create(['source' => 'referral', 'owner_id' => $user->id]);

        $this->assertSame(0, Task::count());
        $run = AutomationRun::firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('conditions_not_met', $run->result['reason']);
    }

    public function test_noop_update_emits_nothing(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, ['trigger_type' => 'lead.status_changed']);

        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id]);
        $this->assertSame(0, AutomationRun::count());

        $lead->update(['first_name' => 'Otro']);
        $this->assertSame(0, AutomationRun::count());
    }

    public function test_lead_status_changed_carries_previous(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, [
            'trigger_type' => 'lead.status_changed',
            'conditions' => [
                ['field' => 'previous_status', 'operator' => 'equals', 'value' => 'new'],
                ['field' => 'status', 'operator' => 'equals', 'value' => 'contacted'],
            ],
        ]);

        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id]);
        $lead->update(['status' => 'contacted']);

        $run = AutomationRun::firstOrFail();
        $this->assertSame('success', $run->status);
        $this->assertSame('new', $run->context['previous_status']);
    }

    // ---------------- decimal ----------------

    public function test_decimal_condition_is_exact(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $firstStage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();

        $this->makeAutomation($user, [
            'trigger_type' => 'opportunity.won',
            'conditions' => [['field' => 'amount', 'operator' => 'greater_or_equal', 'value' => '10000']],
        ]);

        $company = Company::factory()->create(['owner_id' => $user->id]);
        $service = app(OpportunityStageService::class);

        $low = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $firstStage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'open', 'amount' => '9999.99',
        ]);
        $service->move($low, $user, $wonStage->id);

        $high = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $firstStage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
            'status' => 'open', 'amount' => '10000.00',
        ]);
        $service->move($high, $user, $wonStage->id);

        $this->assertSame(1, Task::count());
        $this->assertSame(2, AutomationRun::where('trigger_type', 'opportunity.won')->count());
        $this->assertSame(1, AutomationRun::where('trigger_type', 'opportunity.won')->where('status', 'skipped')->count());
    }

    // ---------------- otros triggers ----------------

    public function test_task_completed_only_on_transition(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, ['trigger_type' => 'task.completed']);

        $task = Task::create([
            'title' => 'Base', 'status' => 'pending', 'priority' => 'medium',
            'assigned_to' => $user->id, 'created_by' => $user->id,
        ]);
        $this->assertSame(0, AutomationRun::count());

        $task->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertSame(1, AutomationRun::count());

        // Guardar de nuevo completada no re-dispara.
        $task->update(['priority' => 'high']);
        $this->assertSame(1, AutomationRun::count());
    }

    public function test_ticket_status_changed_via_service(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, [
            'trigger_type' => 'ticket.status_changed',
            'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'pending']],
        ]);

        $ticket = Ticket::factory()->create(['status' => 'new', 'assigned_to' => $user->id, 'created_by' => $user->id]);
        app(TicketStatusService::class)->transition($ticket, 'pending', $user);

        $this->assertSame(1, Task::count());
        $this->assertSame('success', AutomationRun::firstOrFail()->status);
    }

    public function test_contact_created_trigger(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, ['trigger_type' => 'contact.created']);

        Contact::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(1, Task::count());
    }

    public function test_quote_sale_invoice_campaign_status_triggers(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->makeAutomation($user, ['name' => 'Q', 'trigger_type' => 'quote.status_changed']);
        $this->makeAutomation($user, ['name' => 'S', 'trigger_type' => 'sale.status_changed']);
        $this->makeAutomation($user, ['name' => 'I', 'trigger_type' => 'invoice.status_changed']);
        $this->makeAutomation($user, ['name' => 'C', 'trigger_type' => 'campaign.status_changed']);

        $quote = Quote::factory()->create(['status' => 'draft', 'company_id' => $company->id, 'owner_id' => $user->id]);
        app(\App\Services\Quotes\QuoteService::class)->transition($quote, 'sent', $user);

        $sale = Sale::factory()->create(['status' => 'draft', 'company_id' => $company->id, 'owner_id' => $user->id]);
        app(\App\Services\Sales\SaleCreationService::class)->transition($sale, 'confirmed', $user);

        $invoice = \App\Models\Invoice::factory()->create(['status' => 'draft', 'company_id' => $company->id, 'owner_id' => $user->id]);
        app(\App\Services\Sales\InvoiceCreationService::class)->transition($invoice, 'sent', $user);

        $campaign = Campaign::factory()->create(['status' => 'draft', 'owner_id' => $user->id, 'created_by' => $user->id]);
        $campaign->update(['status' => 'active']);

        $this->assertSame(4, Task::count());
        $this->assertSame(1, AutomationRun::where('trigger_type', 'quote.status_changed')->where('status', 'success')->count());
        $this->assertSame(1, AutomationRun::where('trigger_type', 'sale.status_changed')->where('status', 'success')->count());
        $this->assertSame(1, AutomationRun::where('trigger_type', 'invoice.status_changed')->where('status', 'success')->count());
        $this->assertSame(1, AutomationRun::where('trigger_type', 'campaign.status_changed')->where('status', 'success')->count());
    }

    // ---------------- atomicidad y supervivencia ----------------

    public function test_failed_action_rolls_back_own_actions_but_business_survives(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $fixed = User::factory()->create(['status' => 'active', 'team_id' => $user->team_id]);

        $automation = $this->makeAutomation($user, [
            'actions' => [
                ['type' => 'create_task', 'title' => 'Primera', 'description' => null, 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null],
                ['type' => 'create_task', 'title' => 'Segunda', 'description' => null, 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'fixed_user', 'fixed_user_id' => $fixed->id],
            ],
        ]);

        // El fijo se desactiva tras activar: falla en ejecución, no en config.
        $fixed->update(['status' => 'inactive']);

        $lead = Lead::factory()->create(['source' => 'website', 'owner_id' => $user->id]);

        $this->assertTrue(Lead::whereKey($lead->id)->exists());
        $this->assertSame(0, Task::count());

        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->error_message);
    }

    public function test_defective_automation_does_not_block_others(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $fixed = User::factory()->create(['status' => 'active']);

        $this->makeAutomation($user, ['name' => 'A buena']);
        $bad = $this->makeAutomation($user, [
            'name' => 'B mala',
            'actions' => [
                ['type' => 'create_task', 'title' => 'X', 'description' => null, 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'fixed_user', 'fixed_user_id' => $fixed->id],
            ],
        ]);
        $this->makeAutomation($user, ['name' => 'C buena']);
        $fixed->update(['status' => 'inactive']);

        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(2, Task::count());
        $this->assertSame(2, AutomationRun::where('status', 'success')->count());
        $this->assertSame('failed', AutomationRun::where('automation_id', $bad->id)->firstOrFail()->status);
    }

    // ---------------- dedup + loops ----------------

    public function test_same_event_uuid_executes_once(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        AutomationRun::where('automation_id', $automation->id)->delete();
        Task::query()->delete();

        $uuid = '11111111-2222-4333-8444-555555555555';
        AutomationTriggerDispatcher::dispatch('lead.created', $lead, ['source' => 'website'], $user->id, null, 0, $uuid);
        AutomationTriggerDispatcher::dispatch('lead.created', $lead, ['source' => 'website'], $user->id, null, 0, $uuid);

        $this->assertSame(1, AutomationRun::where('automation_id', $automation->id)->count());
        $this->assertSame(1, Task::count());
    }

    public function test_depth_beyond_limit_does_not_execute(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user);
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $runsBefore = AutomationRun::count();
        $tasksBefore = Task::count();

        AutomationTriggerDispatcher::dispatch('lead.created', $lead, [], $user->id, null, 99);

        $this->assertSame($runsBefore, AutomationRun::count());
        $this->assertSame($tasksBefore, Task::count());
    }

    // ---------------- dry run ----------------

    public function test_dry_run_changes_nothing(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        // Lead primero: su creación no dispara nada (aún no hay automatización).
        $lead = Lead::factory()->create(['source' => 'website', 'owner_id' => $user->id]);
        $automation = $this->makeAutomation($user, [
            'conditions' => [['field' => 'source', 'operator' => 'equals', 'value' => 'website']],
            'actions' => [
                ['type' => 'create_task', 'title' => 'X', 'description' => null, 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null],
                ['type' => 'create_activity', 'subject' => 'Nota auto', 'description' => null, 'actor_mode' => 'automation_owner', 'activity_type' => 'note'],
            ],
        ]);

        $response = $this->actingAs($user)->post("/automations/{$automation->id}/dry-run", [
            'subject_type' => 'lead', 'subject_id' => $lead->id,
        ]);

        $response->assertOk()->assertSee('Se cumplen');
        $this->assertSame(0, Task::count());
        $this->assertSame(0, Activity::count());
        $this->assertSame(0, AutomationRun::count());
    }

    public function test_dry_run_out_of_scope_subject_forbidden(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->fullPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $automation = $this->makeAutomation($vendorA);
        $foreign = Lead::factory()->create(['owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->post("/automations/{$automation->id}/dry-run", [
            'subject_type' => 'lead', 'subject_id' => $foreign->id,
        ])->assertForbidden();
    }

    // ---------------- acciones ----------------

    public function test_create_task_modes_and_relation(): void
    {
        // Owner Gerente (scope de equipo) para poder asignar a un fijo del equipo.
        $team = $this->makeTeam('equipo-t');
        $user = $this->makeUser($this->fullPerms(), $team, ['Gerente comercial']);
        $fixed = $this->makeUser([], $team);

        $this->makeAutomation($user, [
            'actions' => [
                ['type' => 'create_task', 'title' => 'Fija {{subject_id}}', 'description' => null, 'priority' => 'urgent', 'due_in_days' => 0, 'assigned_to_mode' => 'fixed_user', 'fixed_user_id' => $fixed->id],
            ],
        ]);

        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $task = Task::firstOrFail();
        $this->assertSame($fixed->id, $task->assigned_to);
        $this->assertSame('urgent', $task->priority);
        $this->assertSame("Fija {$lead->id}", $task->title);
        $this->assertSame(Lead::class, $task->taskable_type);
    }

    public function test_create_activity_links_and_rejects_system_type(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);

        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'owner_id' => $user->id,
            'actions' => [['type' => 'create_activity', 'subject' => 'Nota base', 'activity_type' => 'status_change', 'actor_mode' => 'automation_owner']],
        ]))->assertSessionHasErrors('actions.0.activity_type');

        $this->makeAutomation($user, [
            'actions' => [['type' => 'create_activity', 'subject' => 'Nota {{subject_name}}', 'description' => null, 'actor_mode' => 'automation_owner', 'activity_type' => 'note']],
        ]);

        $lead = Lead::factory()->create(['first_name' => 'Luis', 'last_name' => 'Paz', 'owner_id' => $user->id]);
        $activity = Activity::firstOrFail();
        $this->assertSame('note', $activity->type);
        $this->assertSame('Nota Luis Paz', $activity->subject);
        $this->assertSame(Lead::class, $activity->subjectable_type);
    }

    public function test_assign_owner_moves_and_rejects_bad_targets(): void
    {
        // Owner Gerente (scope de equipo) para reasignar dentro del equipo.
        $team = $this->makeTeam('equipo-a');
        $user = $this->makeUser($this->fullPerms(), $team, ['Gerente comercial']);
        $mate = $this->makeUser([], $team);

        // Fijo inactivo rechazado en config.
        $off = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'owner_id' => $user->id,
            'actions' => [['type' => 'assign_owner', 'target_mode' => 'fixed_user', 'fixed_user_id' => $off->id]],
        ]))->assertSessionHasErrors('actions.0.fixed_user_id');

        // assign_owner no válido para trigger de tarea.
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'trigger_type' => 'task.completed', 'owner_id' => $user->id,
            'actions' => [['type' => 'assign_owner', 'target_mode' => 'automation_owner']],
        ]))->assertSessionHasErrors('actions.0.type');

        $this->makeAutomation($user, [
            'actions' => [['type' => 'assign_owner', 'target_mode' => 'fixed_user', 'fixed_user_id' => $mate->id]],
        ]);

        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $this->assertSame($mate->id, $lead->fresh()->owner_id);
    }

    public function test_add_to_campaign_adds_once_and_creates_no_communication(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'active']);

        $this->makeAutomation($user, [
            'actions' => [['type' => 'add_to_campaign', 'campaign_id' => $campaign->id]],
        ]);

        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        // Re-disparo manual (mismo lead, nuevo evento): no duplica.
        AutomationTriggerDispatcher::dispatch('lead.created', $lead, [], $user->id);

        $this->assertSame(1, CampaignMember::where('campaign_id', $campaign->id)->count());
        $this->assertSame(0, \App\Models\Communication::count());
    }

    public function test_add_to_campaign_rejects_out_of_scope_campaign(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->fullPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $foreign = Campaign::factory()->create(['owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($vendorA)->post('/automations', $this->automationPayload([
            'owner_id' => $vendorA->id,
            'actions' => [['type' => 'add_to_campaign', 'campaign_id' => $foreign->id]],
        ]))->assertSessionHasErrors('actions.0.campaign_id');
    }

    // ---------------- motor: permisos y scope al ejecutar ----------------

    public function test_owner_losing_permission_skips_without_task(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);

        // Pierde tasks.create después de activar.
        $user->permissions()->sync(
            Permission::whereIn('name', $this->autoPerms())->pluck('id')->all()
        );
        DataScope::clearCache();
        $user->refresh();

        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue(Lead::whereKey($lead->id)->exists());
        $this->assertSame(0, Task::count());
        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('permission_denied', $run->result['reason']);
    }

    public function test_inactive_owner_skips(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);
        $user->update(['status' => 'inactive']);

        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(0, Task::count());
        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('owner_inactive', $run->result['reason']);
    }

    public function test_missing_owner_skips_without_global_fallback(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);
        $automation->update(['owner_id' => null]);

        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(0, Task::count());
        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('owner_missing', $run->result['reason']);
    }

    public function test_scope_loss_skips(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->fullPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $automation = $this->makeAutomation($vendorA, ['trigger_type' => 'lead.status_changed']);

        // Evento en alcance: éxito.
        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $vendorA->id]);
        $lead->update(['status' => 'contacted']);
        $this->assertSame(1, Task::count());
        $this->assertSame('success', AutomationRun::where('automation_id', $automation->id)->firstOrFail()->status);

        // El lead se reasigna fuera del alcance del owner: skip.
        $lead->update(['owner_id' => $vendorB->id, 'status' => 'new']);
        Task::query()->delete();
        AutomationObserver::reset();

        $lead->update(['status' => 'qualified']);

        $this->assertSame(0, Task::count());
        $this->assertTrue(
            AutomationRun::where('automation_id', $automation->id)->where('status', 'skipped')->exists()
        );
    }

    // ---------------- IDOR / roles ----------------

    public function test_idor_vendor_cannot_touch_other_automation(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->fullPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $foreign = $this->makeAutomation($vendorB, ['name' => 'Ajena IDOR']);

        $this->actingAs($vendorA)->get("/automations/{$foreign->id}")->assertForbidden();
        $this->actingAs($vendorA)->put("/automations/{$foreign->id}", $this->automationPayload())->assertForbidden();
        $this->actingAs($vendorA)->delete("/automations/{$foreign->id}")->assertForbidden();
        $this->actingAs($vendorA)->post("/automations/{$foreign->id}/activate")->assertForbidden();
        $this->actingAs($vendorA)->post("/automations/{$foreign->id}/pause")->assertForbidden();
        $this->actingAs($vendorA)->post("/automations/{$foreign->id}/dry-run", ['subject_type' => 'lead', 'subject_id' => 1])->assertForbidden();
        $run = AutomationRun::factory()->create(['automation_id' => $foreign->id]);
        $this->actingAs($vendorA)->get("/automations/{$foreign->id}/runs/{$run->id}")->assertForbidden();
        $this->assertSame('Ajena IDOR', $foreign->fresh()->name);
    }

    public function test_no_privilege_escalation_via_fixed_user(): void
    {
        $vendor = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $admin = $this->makeUser([], null, ['Administrador']);

        $this->actingAs($vendor)->post('/automations', $this->automationPayload([
            'owner_id' => $vendor->id,
            'actions' => [['type' => 'create_task', 'title' => 'X', 'priority' => 'medium', 'due_in_days' => 1, 'assigned_to_mode' => 'fixed_user', 'fixed_user_id' => $admin->id]],
        ]))->assertSessionHasErrors('actions.0.fixed_user_id');

        $this->assertSame(0, Automation::count());
    }

    public function test_consulta_is_read_only(): void
    {
        $consulta = $this->makeUser(
            array_merge($this->autoPerms(), ['leads.view']),
            null, ['Consulta']
        );
        $own = Automation::factory()->create([
            'name' => 'Propia consulta', 'owner_id' => $consulta->id,
            'created_by' => $consulta->id, 'status' => 'draft',
        ]);
        $lead = Lead::factory()->create(['owner_id' => $consulta->id]);

        $this->actingAs($consulta)->get('/automations')->assertOk()->assertSee('Propia consulta');
        $this->actingAs($consulta)->get("/automations/{$own->id}")->assertOk();
        $this->actingAs($consulta)->post('/automations', $this->automationPayload())->assertForbidden();
        $this->actingAs($consulta)->put("/automations/{$own->id}", $this->automationPayload())->assertForbidden();
        $this->actingAs($consulta)->delete("/automations/{$own->id}")->assertForbidden();
        $this->actingAs($consulta)->post("/automations/{$own->id}/activate")->assertForbidden();
        $this->actingAs($consulta)->post("/automations/{$own->id}/dry-run", ['subject_type' => 'lead', 'subject_id' => $lead->id])->assertForbidden();
    }

    public function test_role_less_user_keeps_own_scope(): void
    {
        $roleLess = $this->makeUser($this->autoPerms());
        $other = User::factory()->create(['status' => 'active']);
        Automation::factory()->create(['name' => 'Mía', 'owner_id' => $roleLess->id, 'created_by' => $roleLess->id]);
        $theirs = Automation::factory()->create(['name' => 'Ajena', 'owner_id' => $other->id, 'created_by' => $other->id]);

        $this->actingAs($roleLess)->get('/automations')->assertOk()
            ->assertSee('Mía')->assertDontSee('Ajena');
        $this->actingAs($roleLess)->get("/automations/{$theirs->id}")->assertForbidden();
    }

    public function test_null_team_users_gain_no_mutual_access(): void
    {
        $vendorNull1 = $this->makeUser($this->autoPerms(), null, ['Vendedor']);
        $vendorNull2 = $this->makeUser([], null, ['Vendedor']);
        Automation::factory()->create(['name' => 'Solo N1', 'owner_id' => $vendorNull1->id, 'created_by' => $vendorNull1->id]);
        $hidden = Automation::factory()->create(['name' => 'Solo N2', 'owner_id' => $vendorNull2->id, 'created_by' => $vendorNull2->id]);

        $this->actingAs($vendorNull1)->get('/automations')->assertOk()
            ->assertSee('Solo N1')->assertDontSee('Solo N2');
        $this->actingAs($vendorNull1)->get("/automations/{$hidden->id}")->assertForbidden();
    }

    public function test_supervisor_same_team_can_view(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser([], $teamA, ['Vendedor']);
        $supervisorA = $this->makeUser($this->autoPerms(), $teamA, ['Supervisor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        Automation::factory()->create(['name' => 'Equipo A', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);
        Automation::factory()->create(['name' => 'Equipo B', 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($supervisorA)->get('/automations')->assertOk()
            ->assertSee('Equipo A')->assertDontSee('Equipo B');
    }

    // ---------------- condition engine ----------------

    public function test_condition_operators(): void
    {
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'equals', 'a', 'a'));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'not_equals', 'a', 'b'));
        $this->assertTrue(ConditionEvaluator::evaluate('integer', 'greater_than', 5, 4));
        $this->assertTrue(ConditionEvaluator::evaluate('integer', 'greater_or_equal', 5, 5));
        $this->assertTrue(ConditionEvaluator::evaluate('integer', 'less_than', 4, 5));
        $this->assertTrue(ConditionEvaluator::evaluate('integer', 'less_or_equal', 5, 5));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'in', 'b', ['a', 'b']));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'not_in', 'c', ['a', 'b']));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'contains', 'Hola Mundo', 'mundo'));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'is_null', null, null));
        $this->assertTrue(ConditionEvaluator::evaluate('string', 'not_null', 'x', null));
        $this->assertFalse(ConditionEvaluator::evaluate('string', 'equals', 'a', 'b'));
        $this->assertFalse(ConditionEvaluator::evaluate('string', 'contains', 'hola', 'z'));
        // contains solo strings.
        $this->assertFalse(ConditionEvaluator::evaluate('string', 'contains', 123, '2'));
        // Decimal exacto: 9999.99 < 10000.
        $this->assertTrue(ConditionEvaluator::evaluate('decimal', 'less_than', '9999.99', '10000'));
        $this->assertFalse(ConditionEvaluator::evaluate('decimal', 'greater_or_equal', '9999.99', '10000'));
        $this->assertTrue(ConditionEvaluator::evaluate('decimal', 'greater_or_equal', '10000.00', '10000'));
        $this->assertFalse(ConditionEvaluator::evaluate('decimal', 'greater_or_equal', null, '10000'));
        $this->assertFalse(ConditionEvaluator::evaluate('decimal', 'less_or_equal', null, '10000'));
        $this->assertFalse(ConditionEvaluator::evaluate('decimal', 'equals', 'no-numero', '0'));
    }

    public function test_missing_decimal_condition_value_skips(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $this->makeAutomation($user, [
            'trigger_type' => 'lead.created',
            'conditions' => [
                ['field' => 'estimated_value', 'operator' => 'greater_or_equal', 'value' => '10000'],
            ],
        ]);

        Lead::factory()->create(['estimated_value' => null, 'owner_id' => $user->id]);

        $this->assertSame(0, Task::count());
        $run = AutomationRun::firstOrFail();
        $this->assertSame('skipped', $run->status);
        $this->assertSame('conditions_not_met', $run->result['reason']);
    }

    public function test_condition_limits_and_types_rejected(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);

        $many = [];
        for ($i = 0; $i < 11; $i++) {
            $many[] = ['field' => 'status', 'operator' => 'equals', 'value' => 'new'];
        }
        $this->actingAs($user)->post('/automations', $this->automationPayload(['owner_id' => $user->id, 'conditions' => $many]))
            ->assertSessionHasErrors('conditions');

        // Score con texto no numérico.
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'owner_id' => $user->id,
            'conditions' => [['field' => 'score', 'operator' => 'equals', 'value' => 'alto']],
        ]))->assertSessionHasErrors('conditions.0.value');

        // contains en campo numérico.
        $this->actingAs($user)->post('/automations', $this->automationPayload([
            'owner_id' => $user->id,
            'conditions' => [['field' => 'score', 'operator' => 'contains', 'value' => '7']],
        ]))->assertSessionHasErrors('conditions.0.operator');

        $this->assertSame(0, Automation::count());
    }

    // ---------------- XSS ----------------

    public function test_automation_text_is_escaped(): void
    {
        $user = $this->makeUser($this->autoPerms(), null, ['Vendedor']);
        $automation = Automation::factory()->create([
            'name' => '<script>alert(1)</script>', 'owner_id' => $user->id, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get('/automations')->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($user)->get("/automations/{$automation->id}")->assertOk()
            ->assertSee('&lt;script&gt;', false);
    }

    // ---------------- performance smoke ----------------

    public function test_trigger_only_processes_matching_automations(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);

        for ($i = 0; $i < 50; $i++) {
            Automation::factory()->create([
                'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'active',
                'trigger_type' => 'lead.created', 'conditions' => [],
                'actions' => [['type' => 'create_task', 'title' => "T{$i}", 'description' => null, 'priority' => 'low', 'due_in_days' => 1, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null]],
            ]);
        }

        for ($i = 0; $i < 50; $i++) {
            Automation::factory()->create([
                'owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'active',
                'trigger_type' => 'ticket.status_changed', 'conditions' => [],
                'actions' => [['type' => 'create_task', 'title' => "X{$i}", 'description' => null, 'priority' => 'low', 'due_in_days' => 1, 'assigned_to_mode' => 'subject_owner', 'fixed_user_id' => null]],
            ]);
        }

        Lead::factory()->create(['owner_id' => $user->id]);

        $this->assertSame(50, Task::count());
        $this->assertSame(50, AutomationRun::where('trigger_type', 'lead.created')->count());
        $this->assertSame(0, AutomationRun::where('trigger_type', 'ticket.status_changed')->count());
    }

    // ---------------- run detail y last_run_at ----------------

    public function test_run_detail_and_last_run_at(): void
    {
        $user = $this->makeUser($this->fullPerms(), null, ['Vendedor']);
        $automation = $this->makeAutomation($user);
        $this->assertNull($automation->fresh()->last_run_at);

        Lead::factory()->create(['owner_id' => $user->id]);

        $run = AutomationRun::firstOrFail();
        $this->assertNotNull($automation->fresh()->last_run_at);
        $this->actingAs($user)->get("/automations/{$automation->id}/runs/{$run->id}")->assertOk();
    }
}
