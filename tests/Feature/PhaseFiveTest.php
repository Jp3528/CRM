<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tag;
use App\Models\User;
use App\Services\Leads\LeadConversionService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseFiveTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->all()
        );

        return $user;
    }

    private function fullAccess(): User
    {
        return $this->userWith([
            'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.delete',
        ]);
    }

    private function ventas(): Pipeline
    {
        return Pipeline::where('name', 'Ventas')->firstOrFail();
    }

    private function stage(string $name): PipelineStage
    {
        return $this->ventas()->stages()->where('name', $name)->firstOrFail();
    }

    private function oppPayload(array $overrides = []): array
    {
        $pipeline = $this->ventas();

        return array_merge([
            'name' => 'Oportunidad Acme',
            'description' => 'Propuesta de servicios.',
            'amount' => 5000000.00,
            'currency' => 'USD',
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $this->stage('Prospecto')->id,
            'expected_close_date' => now()->addMonths(2)->format('Y-m-d'),
        ], $overrides);
    }

    private function makeOpp(User $owner, array $overrides = []): Opportunity
    {
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        return Opportunity::factory()->create(array_merge([
            'pipeline_id' => $this->ventas()->id,
            'pipeline_stage_id' => $this->stage('Prospecto')->id,
            'company_id' => $company->id,
            'owner_id' => $owner->id,
            'status' => 'open',
            'probability' => 10,
        ], $overrides));
    }

    // ---------------- CRUD ----------------

    public function test_authorized_user_sees_opportunities_list(): void
    {
        $user = $this->fullAccess();
        $this->makeOpp($user, ['name' => 'Listada S.A.']);

        $this->actingAs($user)->get('/opportunities')->assertOk()->assertSee('Listada S.A.');
    }

    public function test_user_without_permission_cannot_access_opportunities(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $opp = $this->makeOpp(User::factory()->create(['status' => 'active']));

        $this->actingAs($user)->get('/opportunities')->assertForbidden();
        $this->actingAs($user)->get('/opportunities/kanban')->assertForbidden();
        $this->actingAs($user)->get('/opportunities/create')->assertForbidden();
        $this->actingAs($user)->post('/opportunities', $this->oppPayload())->assertForbidden();
        $this->actingAs($user)->get("/opportunities/{$opp->id}")->assertForbidden();
        $this->actingAs($user)->get("/opportunities/{$opp->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/opportunities/{$opp->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete("/opportunities/{$opp->id}")->assertForbidden();
        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Contactado')->id,
        ])->assertForbidden();
    }

    public function test_create_opportunity(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/opportunities', $this->oppPayload([
            'company_id' => $company->id,
            'owner_id' => $user->id,
        ]));

        $opp = Opportunity::where('name', 'Oportunidad Acme')->firstOrFail();
        $response->assertRedirect(route('opportunities.show', $opp));
        $response->assertSessionHas('success', 'Oportunidad creada correctamente.');
        // Probabilidad sincronizada con la etapa, no manual.
        $this->assertSame(10, (int) $opp->probability);
        $this->assertSame('open', $opp->status);
        // Historial inicial registrado.
        $this->assertSame(1, $opp->stageHistory()->count());
        $this->assertNull($opp->stageHistory()->first()->from_stage_id);
    }

    public function test_create_opportunity_validation(): void
    {
        $user = $this->fullAccess();

        $response = $this->actingAs($user)->from('/opportunities/create')->post('/opportunities', $this->oppPayload([
            'name' => '',
            'amount' => -10,
            'currency' => 'XXX',
            'company_id' => 999999,
        ]));

        $response->assertRedirect('/opportunities/create');
        $response->assertSessionHasErrors(['name', 'amount', 'currency', 'company_id']);
    }

    public function test_show_opportunity(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user, ['name' => 'Visible S.A.']);

        $this->actingAs($user)->get("/opportunities/{$opp->id}")->assertOk()->assertSee('Visible S.A.');
    }

    public function test_update_opportunity_and_lead_immutable(): void
    {
        $user = $this->fullAccess();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $opp = $this->makeOpp($user, ['lead_id' => $lead->id]);
        $otherLead = Lead::factory()->create(['owner_id' => $user->id]);

        // Intento de cambiar el lead origen: rechazado.
        $this->actingAs($user)->from("/opportunities/{$opp->id}/edit")->put("/opportunities/{$opp->id}", [
            'name' => $opp->name,
            'currency' => 'USD',
            'company_id' => $opp->company_id,
            'lead_id' => $otherLead->id,
        ])->assertSessionHasErrors('lead_id');
        $this->assertSame($lead->id, $opp->fresh()->lead_id);

        // Actualización normal funciona.
        $response = $this->actingAs($user)->put("/opportunities/{$opp->id}", [
            'name' => 'Actualizada S.A.',
            'currency' => 'COP',
            'amount' => 777.77,
            'company_id' => $opp->company_id,
            'lead_id' => $lead->id,
        ]);
        $response->assertRedirect(route('opportunities.show', $opp));
        $this->assertSame('Actualizada S.A.', $opp->fresh()->name);
    }

    public function test_company_contact_coherence_enforced(): void
    {
        $user = $this->fullAccess();
        $companyA = Company::factory()->create(['owner_id' => $user->id]);
        $companyB = Company::factory()->create(['owner_id' => $user->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->from('/opportunities/create')->post('/opportunities', $this->oppPayload([
            'company_id' => $companyA->id,
            'contact_id' => $contactB->id,
            'owner_id' => $user->id,
        ]));

        $response->assertRedirect('/opportunities/create');
        $response->assertSessionHasErrors('contact_id');
        $this->assertSame(0, Opportunity::count());
    }

    public function test_delete_opportunity_soft_deletes_and_keeps_relations(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $companyId = $opp->company_id;

        // Genera historial antes de eliminar.
        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Contactado')->id,
        ])->assertRedirect();

        $response = $this->actingAs($user)->delete("/opportunities/{$opp->id}");

        $response->assertRedirect(route('opportunities.index'));
        $response->assertSessionHas('success', 'Oportunidad eliminada correctamente.');
        $this->assertSoftDeleted('opportunities', ['id' => $opp->id]);
        $this->assertDatabaseHas('companies', ['id' => $companyId, 'deleted_at' => null]);
        // El historial se conserva (cascade solo ante hard delete).
        $this->assertDatabaseHas('opportunity_stage_history', ['opportunity_id' => $opp->id]);
    }

    public function test_search_opportunities(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['trade_name' => 'BuscadaXYZ Ltda.', 'legal_name' => 'Otra', 'owner_id' => $user->id]);
        $this->makeOpp($user, ['name' => 'Otra cosa', 'company_id' => $company->id]);
        $otherCompany = Company::factory()->create(['trade_name' => 'Descartada Ltda.', 'owner_id' => $user->id]);
        $this->makeOpp($user, ['name' => 'No coincide', 'company_id' => $otherCompany->id]);

        $response = $this->actingAs($user)->get('/opportunities?search=buscadaxyz');

        $response->assertOk();
        $response->assertSee('Otra cosa');
        $response->assertDontSee('No coincide');
    }

    public function test_filter_opportunities(): void
    {
        $user = $this->fullAccess();
        $other = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $this->makeOpp($user, [
            'name' => 'Filtrada Ok', 'status' => 'open',
            'pipeline_stage_id' => $this->stage('Propuesta')->id,
            'company_id' => $company->id, 'amount' => 1000,
            'expected_close_date' => now()->addMonth()->format('Y-m-d'),
        ]);
        $this->makeOpp($other, ['name' => 'Descartada No', 'status' => 'open', 'amount' => 999999]);

        $ventas = $this->ventas()->id;
        $response = $this->actingAs($user)->get(
            "/opportunities?status=open&pipeline_id={$ventas}&pipeline_stage_id={$this->stage('Propuesta')->id}"
            ."&owner_id={$user->id}&company_id={$company->id}&amount_min=500&amount_max=2000"
            .'&close_from='.now()->format('Y-m-d').'&close_to='.now()->addMonths(3)->format('Y-m-d')
        );

        $response->assertOk();
        $response->assertSee('Filtrada Ok');
        $response->assertDontSee('Descartada No');
    }

    public function test_sort_opportunities_by_amount(): void
    {
        $user = $this->fullAccess();
        $this->makeOpp($user, ['amount' => 100]);
        $this->makeOpp($user, ['amount' => 9000]);

        $response = $this->actingAs($user)->get('/opportunities?sort=amount&direction=desc');

        $response->assertOk();
        $items = $response->viewData('opportunities')->items();
        $this->assertGreaterThanOrEqual((float) $items[1]->amount, (float) $items[0]->amount);
    }

    public function test_opportunities_pagination(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Opportunity::factory(16)->create([
            'pipeline_id' => $this->ventas()->id,
            'pipeline_stage_id' => $this->stage('Prospecto')->id,
            'company_id' => $company->id,
            'owner_id' => $user->id,
        ]);

        $pageOne = $this->actingAs($user)->get('/opportunities');
        $pageOne->assertOk();
        $this->assertCount(15, $pageOne->viewData('opportunities')->items());

        $pageTwo = $this->actingAs($user)->get('/opportunities?page=2');
        $pageTwo->assertOk();
        $this->assertCount(1, $pageTwo->viewData('opportunities')->items());
    }

    public function test_opportunity_tags_and_owner(): void
    {
        $user = $this->fullAccess();
        $tag = Tag::create(['name' => 'Enterprise', 'slug' => 'enterprise']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post('/opportunities', $this->oppPayload([
            'name' => 'Con Tags',
            'company_id' => $company->id,
            'owner_id' => $user->id,
            'tags' => [$tag->id],
        ]))->assertRedirect();

        $opp = Opportunity::where('name', 'Con Tags')->firstOrFail();
        $this->assertSame($user->id, $opp->owner_id);
        $this->assertTrue($opp->tags()->whereKey($tag->id)->exists());

        $this->actingAs($user)->delete("/opportunities/{$opp->id}/tags/{$tag->id}")->assertRedirect();
        $this->assertFalse($opp->fresh()->tags()->whereKey($tag->id)->exists());
    }

    // ---------------- Movimiento de etapa ----------------

    public function test_move_stage_creates_history_and_syncs(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);

        $response = $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Contactado')->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $opp = $opp->fresh();
        $this->assertSame('Contactado', $opp->stage->name);
        $this->assertSame(20, (int) $opp->probability);
        $this->assertSame('open', $opp->status);

        $history = $opp->stageHistory()->latest('changed_at')->first();
        $this->assertSame($this->stage('Prospecto')->id, $history->from_stage_id);
        $this->assertSame($this->stage('Contactado')->id, $history->to_stage_id);
        $this->assertSame($user->id, $history->changed_by);

        $this->assertTrue(
            $opp->activities()->where('type', 'status_change')->where('subject', 'like', '%Contactado%')->exists()
        );
    }

    public function test_move_to_same_stage_creates_no_history(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $countBefore = $opp->stageHistory()->count();

        $response = $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Prospecto')->id,
        ]);

        $response->assertRedirect();
        $this->assertSame($countBefore, $opp->fresh()->stageHistory()->count());
    }

    public function test_move_to_other_pipeline_stage_rejected(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $otherPipeline = Pipeline::create(['name' => 'Soporte', 'status' => 'active']);
        $foreign = PipelineStage::create([
            'pipeline_id' => $otherPipeline->id, 'name' => 'Inicial', 'position' => 1, 'probability' => 5,
        ]);
        $historyBefore = $opp->stageHistory()->count();

        $response = $this->actingAs($user)->from("/opportunities/{$opp->id}")
            ->patch("/opportunities/{$opp->id}/stage", ['pipeline_stage_id' => $foreign->id]);

        $response->assertRedirect("/opportunities/{$opp->id}");
        $response->assertSessionHasErrors('pipeline_stage_id');
        $this->assertSame($this->stage('Prospecto')->id, $opp->fresh()->pipeline_stage_id);
        $this->assertSame($historyBefore, $opp->fresh()->stageHistory()->count());
    }

    public function test_move_won_syncs_correctly(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);

        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Ganada')->id,
        ])->assertRedirect();

        $opp = $opp->fresh();
        $this->assertSame('won', $opp->status);
        $this->assertSame(100, (int) $opp->probability);
        $this->assertNotNull($opp->actual_close_date);
        $this->assertNull($opp->loss_reason);
        $this->assertSame(1, $opp->stageHistory()->count());
    }

    public function test_move_lost_requires_reason_and_syncs(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);

        // Sin motivo: rechazado, nada cambia.
        $this->actingAs($user)->from("/opportunities/{$opp->id}")
            ->patch("/opportunities/{$opp->id}/stage", [
                'pipeline_stage_id' => $this->stage('Perdida')->id,
            ])->assertSessionHasErrors('loss_reason');
        $this->assertSame('open', $opp->fresh()->status);

        // Con motivo: sincroniza.
        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Perdida')->id,
            'loss_reason' => 'Precio alto',
        ])->assertRedirect();

        $opp = $opp->fresh();
        $this->assertSame('lost', $opp->status);
        $this->assertSame(0, (int) $opp->probability);
        $this->assertNotNull($opp->actual_close_date);
        $this->assertSame('Precio alto', $opp->loss_reason);
    }

    public function test_reopen_lost_clears_close_data(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Perdida')->id,
            'loss_reason' => 'Sin presupuesto',
        ]);
        $this->assertSame('lost', $opp->fresh()->status);

        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Negociación')->id,
        ])->assertRedirect();

        $opp = $opp->fresh();
        $this->assertSame('open', $opp->status);
        $this->assertNull($opp->actual_close_date);
        $this->assertNull($opp->loss_reason);
        $this->assertSame(80, (int) $opp->probability);
        $this->assertSame(2, $opp->stageHistory()->count());
    }

    public function test_reopen_won_clears_close_date(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Ganada')->id,
        ]);
        $this->assertSame('won', $opp->fresh()->status);

        $this->actingAs($user)->patch("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Propuesta')->id,
        ])->assertRedirect();

        $opp = $opp->fresh();
        $this->assertSame('open', $opp->status);
        $this->assertNull($opp->actual_close_date);
        $this->assertSame(60, (int) $opp->probability);
    }

    public function test_failed_move_leaves_everything_unchanged(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);
        $historyBefore = $opp->stageHistory()->count();
        $activitiesBefore = $opp->activities()->count();

        // Etapa inexistente: la validación exists la rechaza antes del servicio;
        // nada persiste (transacción intacta).
        $this->actingAs($user)->from("/opportunities/{$opp->id}")
            ->patch("/opportunities/{$opp->id}/stage", [
                'pipeline_stage_id' => 999999,
            ])->assertSessionHasErrors('pipeline_stage_id');

        $this->assertSame($this->stage('Prospecto')->id, $opp->fresh()->pipeline_stage_id);
        $this->assertSame($historyBefore, $opp->fresh()->stageHistory()->count());
        $this->assertSame($activitiesBefore, $opp->fresh()->activities()->count());
    }

    public function test_move_endpoint_returns_json_for_kanban(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user);

        $response = $this->actingAs($user)->patchJson("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Contactado')->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('moved', true);
        $response->assertJsonPath('opportunity.status', 'open');
        $response->assertJsonPath('opportunity.probability', 20);
    }

    // ---------------- Kanban ----------------

    public function test_kanban_renders_columns_totals_and_cards(): void
    {
        $user = $this->fullAccess();
        $this->makeOpp($user, ['name' => 'Kanban Uno', 'amount' => 100.00]);
        $this->makeOpp($user, ['name' => 'Kanban Dos', 'amount' => 200.00]);

        $response = $this->actingAs($user)->get('/opportunities/kanban');

        $response->assertOk();
        foreach (['Prospecto', 'Contactado', 'Necesidad identificada', 'Propuesta', 'Negociación', 'Ganada', 'Perdida'] as $stage) {
            $response->assertSee($stage);
        }
        $response->assertSee('Kanban Uno');
        $response->assertSee('Kanban Dos');

        $totals = $response->viewData('totals');
        $prospectoId = $this->stage('Prospecto')->id;
        $this->assertSame(2, $totals[$prospectoId]['count']);
        $this->assertEquals(300, $totals[$prospectoId]['amount']);
    }

    public function test_kanban_shows_moved_card_in_new_column(): void
    {
        $user = $this->fullAccess();
        $opp = $this->makeOpp($user, ['name' => 'Movida Kanban']);

        $this->actingAs($user)->patchJson("/opportunities/{$opp->id}/stage", [
            'pipeline_stage_id' => $this->stage('Propuesta')->id,
        ])->assertOk();

        $response = $this->actingAs($user)->get('/opportunities/kanban');
        $stages = $response->viewData('stages');
        $propuesta = $stages->firstWhere('name', 'Propuesta');
        $this->assertTrue($propuesta->opportunities->contains('id', $opp->id));
    }

    // ---------------- Compatibilidad Fase 4 ----------------

    public function test_conversion_opportunities_appear_in_list_detail_kanban(): void
    {
        $user = $this->userWith([
            'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.delete',
            'leads.view', 'leads.convert',
        ]);
        $lead = Lead::factory()->create(['status' => 'qualified', 'owner_id' => $user->id]);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);

        $service = app(LeadConversionService::class);
        $result = $service->convert($lead, $user, [
            'company_mode' => 'existing',
            'company_id' => $company->id,
            'contact_mode' => 'existing',
            'contact_id' => $contact->id,
            'create_opportunity' => true,
            'opportunity_name' => 'Opp desde Lead',
        ]);

        $this->assertNotNull($result['opportunity']);

        $this->actingAs($user)->get('/opportunities')->assertOk()->assertSee('Opp desde Lead');
        $this->actingAs($user)->get("/opportunities/{$result['opportunity']->id}")->assertOk();
        $this->actingAs($user)->get('/opportunities/kanban')->assertOk()->assertSee('Opp desde Lead');
    }
}
