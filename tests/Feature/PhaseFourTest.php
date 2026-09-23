<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseFourTest extends TestCase
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

    private function fullLeadAccess(): User
    {
        return $this->userWith([
            'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
        ]);
    }

    private function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'company_name' => 'Gómez y Cía.',
            'email' => 'ana.gomez@example.com',
            'phone' => '+57 601 555 0303',
            'source' => 'website',
            'status' => 'new',
            'score' => 55,
            'estimated_value' => 15000000.00,
            'notes' => 'Interesada en demo.',
        ], $overrides);
    }

    private function qualifiedLead(User $owner, array $overrides = []): Lead
    {
        return Lead::factory()->create(array_merge([
            'status' => 'qualified',
            'owner_id' => $owner->id,
        ], $overrides));
    }

    // ---------------- CRUD ----------------

    public function test_authorized_user_sees_leads_list(): void
    {
        $user = $this->fullLeadAccess();
        Lead::factory()->create(['first_name' => 'Listado', 'last_name' => 'Visible', 'owner_id' => $user->id]);

        $this->actingAs($user)->get('/leads')->assertOk()->assertSee('Listado Visible');
    }

    public function test_user_without_permission_cannot_access_leads(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $lead = Lead::factory()->create();

        $this->actingAs($user)->get('/leads')->assertForbidden();
        $this->actingAs($user)->get('/leads/create')->assertForbidden();
        $this->actingAs($user)->post('/leads', $this->leadPayload())->assertForbidden();
        $this->actingAs($user)->get("/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($user)->get("/leads/{$lead->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/leads/{$lead->id}", $this->leadPayload())->assertForbidden();
        $this->actingAs($user)->delete("/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($user)->get("/leads/{$lead->id}/convert")->assertForbidden();
        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [])->assertForbidden();
    }

    public function test_create_lead(): void
    {
        $user = $this->fullLeadAccess();

        $response = $this->actingAs($user)->post('/leads', $this->leadPayload(['owner_id' => $user->id]));

        $lead = Lead::where('email', 'ana.gomez@example.com')->firstOrFail();
        $response->assertRedirect(route('leads.show', $lead));
        $response->assertSessionHas('success', 'Lead creado correctamente.');
        $this->assertSame(55, $lead->score);
    }

    public function test_create_lead_validation(): void
    {
        $user = $this->fullLeadAccess();

        $response = $this->actingAs($user)->from('/leads/create')->post('/leads', $this->leadPayload([
            'first_name' => '',
            'email' => 'mal',
            'source' => 'inexistente',
            'status' => 'raro',
            'score' => 101,
            'estimated_value' => -5,
        ]));

        $response->assertRedirect('/leads/create');
        $response->assertSessionHasErrors(['first_name', 'email', 'source', 'status', 'score', 'estimated_value']);
        $this->assertDatabaseMissing('leads', ['email' => 'mal']);
    }

    public function test_show_lead(): void
    {
        $user = $this->fullLeadAccess();
        $lead = Lead::factory()->create(['first_name' => 'Visible', 'last_name' => 'Lead', 'owner_id' => $user->id]);

        $this->actingAs($user)->get("/leads/{$lead->id}")->assertOk()->assertSee('Visible Lead');
    }

    public function test_update_lead(): void
    {
        $user = $this->fullLeadAccess();
        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->put("/leads/{$lead->id}", $this->leadPayload([
            'status' => 'contacted',
            'score' => 70,
        ]));

        $response->assertRedirect(route('leads.show', $lead));
        $response->assertSessionHas('success', 'Lead actualizado correctamente.');
        $this->assertSame('contacted', $lead->fresh()->status);
    }

    public function test_converted_lead_cannot_be_edited(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Histórica S.A.S.',
            'contact_mode' => 'new',
        ])->assertRedirect();

        // Edición directa bloqueada (403 vía FormRequest).
        $this->actingAs($user)->put("/leads/{$lead->id}", $this->leadPayload())->assertForbidden();
        // Página de edición redirige con error.
        $this->actingAs($user)->get("/leads/{$lead->id}/edit")->assertRedirect();
    }

    public function test_delete_lead_soft_deletes(): void
    {
        $user = $this->fullLeadAccess();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/leads/{$lead->id}");

        $response->assertRedirect(route('leads.index'));
        $response->assertSessionHas('success', 'Lead eliminado correctamente.');
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_search_leads(): void
    {
        $user = $this->fullLeadAccess();
        Lead::factory()->create(['first_name' => 'BuscadoXYZ', 'last_name' => 'Uno', 'company_name' => 'Otra', 'email' => 'a@a.com', 'phone' => '111', 'owner_id' => $user->id]);
        Lead::factory()->create(['first_name' => 'Otro', 'last_name' => 'Cualquiera', 'company_name' => 'EmpresaZZZ', 'email' => 'b@b.com', 'phone' => '222', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/leads?search=buscadoxYZ');

        $response->assertOk();
        $response->assertSee('BuscadoXYZ');
        $response->assertDontSee('EmpresaZZZ');
    }

    public function test_filter_leads(): void
    {
        $user = $this->fullLeadAccess();
        $other = User::factory()->create(['status' => 'active']);
        Lead::factory()->create([
            'first_name' => 'Filtrado', 'last_name' => 'Ok', 'status' => 'qualified',
            'source' => 'website', 'score' => 80, 'owner_id' => $user->id,
        ]);
        Lead::factory()->create([
            'first_name' => 'Descartado', 'last_name' => 'No', 'status' => 'new',
            'source' => 'event', 'score' => 10, 'owner_id' => $other->id,
        ]);

        $response = $this->actingAs($user)->get(
            "/leads?status=qualified&source=website&owner_id={$user->id}&score_min=50&score_max=90&converted=no"
        );

        $response->assertOk();
        $response->assertSee('Filtrado Ok');
        $response->assertDontSee('Descartado No');
    }

    public function test_sort_leads_by_score(): void
    {
        $user = $this->fullLeadAccess();
        Lead::factory()->create(['first_name' => 'Bajo', 'last_name' => 'S', 'score' => 5, 'owner_id' => $user->id]);
        Lead::factory()->create(['first_name' => 'Alto', 'last_name' => 'S', 'score' => 95, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->get('/leads?sort=score&direction=desc');

        $response->assertOk();
        $items = $response->viewData('leads')->items();
        $this->assertGreaterThanOrEqual($items[1]->score, $items[0]->score);
    }

    public function test_leads_pagination(): void
    {
        $user = $this->fullLeadAccess();
        Lead::factory(16)->create(['owner_id' => $user->id]);

        $pageOne = $this->actingAs($user)->get('/leads');
        $pageOne->assertOk();
        $this->assertCount(15, $pageOne->viewData('leads')->items());

        $pageTwo = $this->actingAs($user)->get('/leads?page=2');
        $pageTwo->assertOk();
        $this->assertCount(1, $pageTwo->viewData('leads')->items());
    }

    public function test_lead_tags_and_owner(): void
    {
        $user = $this->fullLeadAccess();
        $tag = Tag::create(['name' => 'Feria', 'slug' => 'feria']);

        $this->actingAs($user)->post('/leads', $this->leadPayload([
            'owner_id' => $user->id,
            'tags' => [$tag->id],
            'new_tags' => 'Referido',
        ]))->assertRedirect();

        $lead = Lead::where('email', 'ana.gomez@example.com')->firstOrFail();
        $this->assertSame($user->id, $lead->owner_id);
        $this->assertTrue($lead->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($lead->tags()->where('slug', 'referido')->exists());

        $this->actingAs($user)->delete("/leads/{$lead->id}/tags/{$tag->id}")->assertRedirect();
        $this->assertFalse($lead->fresh()->tags()->whereKey($tag->id)->exists());
    }

    public function test_qualify_action(): void
    {
        $user = $this->fullLeadAccess();
        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->patch("/leads/{$lead->id}/qualify");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lead calificado correctamente. Ya puede convertirse.');
        $this->assertSame('qualified', $lead->fresh()->status);
    }

    // ---------------- Conversión ----------------

    public function test_qualified_lead_converts_to_company_and_contact(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user, [
            'first_name' => 'Convertible', 'last_name' => 'Uno',
            'company_name' => 'Convertida S.A.S.',
            'email' => 'lead@convertida.com', 'phone' => '333',
        ]);

        $response = $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Convertida S.A.S.',
            'contact_mode' => 'new',
        ]);

        $response->assertRedirect(route('leads.show', $lead->id));
        $response->assertSessionHas('success');

        $lead = $lead->fresh();
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertNotNull($lead->converted_company_id);
        $this->assertNotNull($lead->converted_contact_id);

        $company = Company::findOrFail($lead->converted_company_id);
        $this->assertSame('Convertida S.A.S.', $company->trade_name);
        // El email del lead es de la persona: no se copia como email corporativo.
        $this->assertNull($company->email);

        $contact = Contact::findOrFail($lead->converted_contact_id);
        $this->assertSame('Convertible', $contact->first_name);
        $this->assertSame('lead@convertida.com', $contact->email);
        $this->assertSame($company->id, $contact->company_id);
    }

    public function test_conversion_links_existing_company(): void
    {
        $user = $this->fullLeadAccess();
        $existing = Company::factory()->create(['trade_name' => 'Existente S.A.S.', 'owner_id' => $user->id]);
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'existing',
            'company_id' => $existing->id,
            'contact_mode' => 'new',
        ])->assertRedirect();

        $this->assertSame($existing->id, $lead->fresh()->converted_company_id);
        $this->assertSame(1, Company::where('trade_name', 'Existente S.A.S.')->count());
    }

    public function test_conversion_creates_opportunity_on_ventas_prospecto(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user, ['estimated_value' => 25000000.00]);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'ConOportunidad S.A.S.',
            'contact_mode' => 'new',
            'create_opportunity' => '1',
            'opportunity_name' => 'Oportunidad Convertida',
            'expected_close_date' => now()->addMonth()->format('Y-m-d'),
        ])->assertRedirect();

        $opportunity = Opportunity::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('Oportunidad Convertida', $opportunity->name);
        // estimated_value del lead se propone como amount.
        $this->assertEquals('25000000.00', $opportunity->amount);
        $this->assertSame('Ventas', $opportunity->pipeline->name);
        $this->assertSame('Prospecto', $opportunity->stage->name);
        $this->assertSame($lead->fresh()->converted_company_id, $opportunity->company_id);
        $this->assertSame($lead->fresh()->converted_contact_id, $opportunity->contact_id);
    }

    public function test_conversion_opportunity_amount_can_be_reviewed(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user, ['estimated_value' => 1000]);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Monto Revisado S.A.S.',
            'contact_mode' => 'new',
            'create_opportunity' => '1',
            'opportunity_name' => 'Opp Revisada',
            'opportunity_amount' => 9999.99,
        ])->assertRedirect();

        $this->assertEquals('9999.99', Opportunity::where('lead_id', $lead->id)->firstOrFail()->amount);
    }

    public function test_converted_lead_cannot_be_reconverted(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Única S.A.S.',
            'contact_mode' => 'new',
        ])->assertRedirect();

        $companies = Company::count();
        $contacts = Contact::count();

        // Segunda solicitud: rechazada, sin duplicados.
        $response = $this->actingAs($user)->from("/leads/{$lead->id}")
            ->post("/leads/{$lead->id}/convert", [
                'company_mode' => 'new',
                'company_name' => 'Duplicada S.A.S.',
                'contact_mode' => 'new',
            ]);

        $response->assertRedirect("/leads/{$lead->id}");
        $response->assertSessionHasErrors('lead');
        $this->assertSame($companies, Company::count());
        $this->assertSame($contacts, Contact::count());
        $this->assertDatabaseMissing('companies', ['trade_name' => 'Duplicada S.A.S.']);
    }

    public function test_non_qualified_lead_cannot_convert(): void
    {
        $user = $this->fullLeadAccess();
        $lead = Lead::factory()->create(['status' => 'new', 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->from("/leads/{$lead->id}")
            ->post("/leads/{$lead->id}/convert", [
                'company_mode' => 'new',
                'company_name' => 'NoDebe S.A.S.',
                'contact_mode' => 'new',
            ]);

        $response->assertRedirect("/leads/{$lead->id}");
        $response->assertSessionHasErrors('lead');
        $this->assertSame('new', $lead->fresh()->status);
        $this->assertDatabaseMissing('companies', ['trade_name' => 'NoDebe S.A.S.']);
    }

    public function test_user_without_convert_permission_cannot_convert(): void
    {
        $user = $this->userWith(['leads.view', 'leads.create', 'leads.update', 'leads.delete']);
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->get("/leads/{$lead->id}/convert")->assertForbidden();
        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'SinPermiso S.A.S.',
            'contact_mode' => 'new',
        ])->assertForbidden();
        $this->assertDatabaseMissing('companies', ['trade_name' => 'SinPermiso S.A.S.']);
    }

    public function test_failed_conversion_rolls_back_everything(): void
    {
        $user = $this->fullLeadAccess();
        $otherCompany = Company::factory()->create(['owner_id' => $user->id]);
        $foreignContact = Contact::factory()->create(['company_id' => $otherCompany->id, 'owner_id' => $user->id]);
        $lead = $this->qualifiedLead($user);

        $companiesBefore = Company::count();
        $contactsBefore = Contact::count();

        // La empresa se crearía primero, pero el contacto es de otra empresa:
        // la transacción debe revertir también la empresa.
        $response = $this->actingAs($user)->from("/leads/{$lead->id}/convert")->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Revertida S.A.S.',
            'contact_mode' => 'existing',
            'contact_id' => $foreignContact->id,
        ]);

        $response->assertRedirect("/leads/{$lead->id}/convert");
        $response->assertSessionHasErrors('contact_id');
        $this->assertSame($companiesBefore, Company::count());
        $this->assertSame($contactsBefore, Contact::count());
        $this->assertDatabaseMissing('companies', ['trade_name' => 'Revertida S.A.S.']);
        $this->assertFalse($lead->fresh()->isConverted());
    }

    public function test_existing_contact_without_company_gets_linked(): void
    {
        $user = $this->fullLeadAccess();
        $contact = Contact::factory()->create(['company_id' => null, 'owner_id' => $user->id]);
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'Vinculante S.A.S.',
            'contact_mode' => 'existing',
            'contact_id' => $contact->id,
        ])->assertRedirect();

        $lead = $lead->fresh();
        $this->assertSame($contact->id, $lead->converted_contact_id);
        $this->assertSame($lead->converted_company_id, $contact->fresh()->company_id);
    }

    public function test_conversion_registers_activity(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
            'company_mode' => 'new',
            'company_name' => 'ConActividad S.A.S.',
            'contact_mode' => 'new',
        ])->assertRedirect();

        $activity = $lead->activities()->where('type', 'status_change')->first();
        $this->assertNotNull($activity);
        $this->assertSame('Lead convertido', $activity->subject);
    }

    public function test_convert_screen_renders(): void
    {
        $user = $this->fullLeadAccess();
        $lead = $this->qualifiedLead($user);

        $this->actingAs($user)->get("/leads/{$lead->id}/convert")
            ->assertOk()
            ->assertSee('Convertir');
    }

    public function test_pipeline_ventas_and_prospecto_used(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);

        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $first = $pipeline->stages()->orderBy('position')->firstOrFail();

        $this->assertSame('Prospecto', $first->name);
        $this->assertFalse($first->is_won);
        $this->assertFalse($first->is_lost);
    }
}
