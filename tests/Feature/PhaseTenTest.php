<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Campaigns\CampaignCommunicationService;
use App\Support\DataScope;
use App\Support\TemplateVariables;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Fase 10 — Campañas + comunicaciones (todo simulado, sin proveedor externo).
 */
class PhaseTenTest extends TestCase
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
            $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);
        }

        return $user->fresh();
    }

    /** @return array<int,string> */
    private function marketingPerms(): array
    {
        return [
            'campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.delete',
            'templates.view', 'templates.create', 'templates.update', 'templates.delete',
            'communications.view', 'communications.create', 'communications.update', 'communications.delete',
            'contacts.view', 'leads.view',
        ];
    }

    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Campaña Q4',
            'description' => 'Lanzamiento interno.',
            'type' => 'email',
            'status' => 'draft',
        ], $overrides);
    }

    // ---------------- Campaign CRUD ----------------

    public function test_campaign_list_requires_permission(): void
    {
        $user = $this->makeUser([], null, ['Vendedor']);
        $this->actingAs($user)->get('/campaigns')->assertForbidden();
    }

    public function test_campaign_create_and_show(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);

        $response = $this->actingAs($user)->post('/campaigns', $this->campaignPayload());
        $response->assertRedirect();

        $campaign = Campaign::firstOrFail();
        $this->assertSame('Campaña Q4', $campaign->name);
        $this->assertSame($user->id, $campaign->owner_id);

        $this->actingAs($user)->get("/campaigns/{$campaign->id}")->assertOk()->assertSee('Campaña Q4');
    }

    public function test_campaign_validation_rejects_bad_type_and_dates(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);

        $this->actingAs($user)->post('/campaigns', $this->campaignPayload(['type' => 'carrier_pigeon']))
            ->assertSessionHasErrors('type');

        $this->actingAs($user)->post('/campaigns', $this->campaignPayload([
            'start_at' => '2026-02-01', 'end_at' => '2026-01-01',
        ]))->assertSessionHasErrors('end_at');

        $this->assertSame(0, Campaign::count());
    }

    public function test_campaign_owner_out_of_scope_rejected(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        $this->actingAs($vendorA)->post('/campaigns', $this->campaignPayload(['owner_id' => $vendorB->id]))
            ->assertForbidden();
        $this->assertSame(0, Campaign::count());
    }

    public function test_campaign_update_and_invalid_transition_rejected(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id, 'status' => 'draft']);

        $this->actingAs($user)->put("/campaigns/{$campaign->id}", $this->campaignPayload(['name' => 'Nueva', 'status' => 'active']))
            ->assertRedirect();
        $this->assertSame('active', $campaign->fresh()->status);

        // active -> draft no permitido.
        $this->actingAs($user)->put("/campaigns/{$campaign->id}", $this->campaignPayload(['name' => 'Nueva', 'status' => 'draft']))
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $campaign->fresh()->status);
    }

    public function test_campaign_soft_delete(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->delete("/campaigns/{$campaign->id}")->assertRedirect();
        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
    }

    public function test_campaign_search_filters_sorting_pagination(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        Campaign::factory()->create(['name' => 'Alpha lanzamiento', 'description' => 'x', 'type' => 'email', 'status' => 'draft', 'owner_id' => $user->id, 'created_by' => $user->id, 'budget' => 100]);
        Campaign::factory()->create(['name' => 'Beta evento', 'description' => 'y', 'type' => 'event', 'status' => 'active', 'owner_id' => $user->id, 'created_by' => $user->id, 'budget' => 900]);

        $this->actingAs($user)->get('/campaigns?search=alpha')->assertOk()->assertSee('Alpha')->assertDontSee('Beta');
        $this->actingAs($user)->get('/campaigns?status=active')->assertOk()->assertSee('Beta')->assertDontSee('Alpha');
        $this->actingAs($user)->get('/campaigns?type=email')->assertOk()->assertSee('Alpha')->assertDontSee('Beta');

        $sorted = $this->actingAs($user)->get('/campaigns?sort=budget&direction=asc');
        $sorted->assertOk();
        $names = $sorted->viewData('campaigns')->pluck('name')->all();
        $this->assertSame(['Alpha lanzamiento', 'Beta evento'], $names);
    }

    public function test_campaign_scope_vendor_only_own(): void
    {
        $teamA = $this->makeTeam('a');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $mate = $this->makeUser([], $teamA, ['Vendedor']);

        Campaign::factory()->create(['name' => 'Mía visible', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);
        $theirs = Campaign::factory()->create(['name' => 'Ajena oculta', 'owner_id' => $mate->id, 'created_by' => $mate->id]);

        $this->actingAs($vendorA)->get('/campaigns')->assertOk()
            ->assertSee('Mía visible')->assertDontSee('Ajena oculta');
        $this->actingAs($vendorA)->get("/campaigns/{$theirs->id}")->assertForbidden();
    }

    // ---------------- Members ----------------

    public function test_add_contact_and_lead_members(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);
        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $lead = Lead::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'contact', 'member_id' => $contact->id,
        ])->assertRedirect();
        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'lead', 'member_id' => $lead->id,
        ])->assertRedirect();

        $this->assertSame(2, $campaign->members()->count());
    }

    public function test_member_duplicate_rejected(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);
        $contact = Contact::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'contact', 'member_id' => $contact->id,
        ])->assertRedirect();
        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'contact', 'member_id' => $contact->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, $campaign->members()->count());
    }

    public function test_member_out_of_scope_rejected(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);
        $foreignContact = Contact::factory()->create(['owner_id' => $vendorB->id]);
        $foreignLead = Lead::factory()->create(['owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'contact', 'member_id' => $foreignContact->id,
        ])->assertForbidden();
        $this->actingAs($vendorA)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'lead', 'member_id' => $foreignLead->id,
        ])->assertForbidden();

        $this->assertSame(0, $campaign->members()->count());
    }

    public function test_member_morph_arbitrary_rejected(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members", [
            'member_type' => 'user', 'member_id' => $user->id,
        ])->assertSessionHasErrors('member_type');

        $this->assertSame(0, $campaign->members()->count());
    }

    public function test_remove_member_and_block_with_history(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);
        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $member = CampaignMember::create([
            'campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $contact->id,
            'status' => 'pending', 'added_by' => $user->id,
        ]);

        $this->actingAs($user)->delete("/campaigns/{$campaign->id}/members/{$member->id}")->assertRedirect();
        $this->assertSame(0, $campaign->members()->count());

        // Con comunicaciones asociadas no se puede quitar.
        $member2 = CampaignMember::create([
            'campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $contact->id,
            'status' => 'sent', 'added_by' => $user->id,
        ]);
        Communication::factory()->create([
            'campaign_id' => $campaign->id, 'campaign_member_id' => $member2->id,
            'contact_id' => $contact->id, 'owner_id' => $user->id, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->delete("/campaigns/{$campaign->id}/members/{$member2->id}")
            ->assertSessionHas('error');
        $this->assertSame(1, $campaign->members()->count());
    }

    public function test_member_counts_are_scoped(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $campaign = Campaign::factory()->create(['name' => 'Conteo mixto', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);

        $mine = Contact::factory()->create(['owner_id' => $vendorA->id]);
        $foreign = Contact::factory()->create(['owner_id' => $vendorB->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $mine->id, 'added_by' => $vendorA->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $foreign->id, 'added_by' => $vendorA->id]);

        $response = $this->actingAs($vendorA)->get('/campaigns');
        $response->assertOk();
        $row = $response->viewData('campaigns')->firstWhere('id', $campaign->id);
        $this->assertSame(1, (int) $row->scoped_members_count);

        // En ficha tampoco se ve el nombre ajeno.
        $show = $this->actingAs($vendorA)->get("/campaigns/{$campaign->id}");
        $show->assertOk()
            ->assertSee($mine->first_name)
            ->assertDontSee($foreign->email);
    }

    // ---------------- Audience builder ----------------

    public function test_audience_builder_filters_and_bulk_add(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);

        Contact::factory()->create(['status' => 'active', 'owner_id' => $user->id, 'first_name' => 'Ana']);
        Contact::factory()->create(['status' => 'inactive', 'owner_id' => $user->id, 'first_name' => 'Beto']);
        Lead::factory()->create(['status' => 'new', 'source' => 'website', 'score' => 80, 'owner_id' => $user->id]);
        Lead::factory()->create(['status' => 'new', 'source' => 'website', 'score' => 10, 'owner_id' => $user->id]);

        $preview = $this->actingAs($user)->get("/campaigns/{$campaign->id}/audience?tab=contacts&contact_status=active");
        $preview->assertOk();
        $this->assertSame(1, $preview->viewData('contactPreview'));

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members/bulk", [
            'tab' => 'contacts', 'contact_status' => 'active',
        ])->assertRedirect();
        $this->assertSame(1, $campaign->members()->where('member_type', 'contact')->count());

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/members/bulk", [
            'tab' => 'leads', 'lead_score_min' => 50,
        ])->assertRedirect();
        $this->assertSame(1, $campaign->members()->where('member_type', 'lead')->count());
    }

    public function test_audience_bulk_respects_scope_and_no_duplicates(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);

        Contact::factory()->create(['owner_id' => $vendorA->id]);
        Contact::factory()->create(['owner_id' => $vendorB->id]);

        $this->actingAs($vendorA)->post("/campaigns/{$campaign->id}/members/bulk", ['tab' => 'contacts'])
            ->assertRedirect();
        $this->assertSame(1, $campaign->members()->count());

        // Segunda vez no duplica.
        $this->actingAs($vendorA)->post("/campaigns/{$campaign->id}/members/bulk", ['tab' => 'contacts'])
            ->assertRedirect();
        $this->assertSame(1, $campaign->members()->count());
    }

    // ---------------- Templates ----------------

    public function test_template_crud_and_scope(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);

        $this->actingAs($user)->post('/templates', [
            'name' => 'Bienvenida', 'channel' => 'email', 'subject' => 'Hola', 'body' => 'Hola {{full_name}}',
        ])->assertRedirect();

        $template = MessageTemplate::firstOrFail();
        $this->actingAs($user)->get("/templates/{$template->id}")->assertOk()->assertSee('Bienvenida');

        // XSS escapado.
        $xss = MessageTemplate::factory()->create([
            'name' => 'XSS', 'body' => '<script>alert(1)</script>', 'owner_id' => $user->id, 'created_by' => $user->id,
        ]);
        $this->actingAs($user)->get("/templates/{$xss->id}")->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_template_variables_whitelist_and_unknown_safe(): void
    {
        $this->assertSame('Hola Ana Pérez', TemplateVariables::render(
            'Hola {{first_name}} {{last_name}}',
            ['first_name' => 'Ana', 'last_name' => 'Pérez']
        ));

        // Variable desconocida no se sustituye con datos ni se ejecuta.
        $rendered = TemplateVariables::render('Hola {{evil}} {{first_name}}', ['first_name' => 'Ana']);
        $this->assertSame('Hola {{evil}} Ana', $rendered);

        // Preview con ejemplo seguro.
        $template = new MessageTemplate(['body' => 'Hola {{full_name}} de {{company_name}} {{unknown}}']);
        $preview = $template->preview();
        $this->assertStringContainsString('Nombre Ejemplo', $preview);
        $this->assertStringContainsString('{{unknown}}', $preview);
    }

    public function test_template_idor(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $foreign = MessageTemplate::factory()->create(['owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($vendorA)->get("/templates/{$foreign->id}")->assertForbidden();
        $this->actingAs($vendorA)->put("/templates/{$foreign->id}", [
            'name' => 'Hack', 'channel' => 'email', 'body' => 'x', 'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($vendorA)->delete("/templates/{$foreign->id}")->assertForbidden();
    }

    // ---------------- Communications ----------------

    public function test_communication_create_and_simulate(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);
        $member = CampaignMember::create([
            'campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $contact->id, 'added_by' => $user->id,
        ]);

        $this->actingAs($user)->post('/communications', [
            'campaign_id' => $campaign->id,
            'campaign_member_id' => $member->id,
            'contact_id' => $contact->id,
            'channel' => 'email',
            'subject' => 'Promo',
            'body' => 'Hola {{full_name}}',
        ])->assertRedirect();

        $comm = Communication::firstOrFail();
        $this->assertSame('draft', $comm->status);
        $this->assertNull($comm->sent_at);

        $this->actingAs($user)->patch("/communications/{$comm->id}/simulate")->assertRedirect();
        $comm->refresh();
        $this->assertSame('simulated_sent', $comm->status);
        $this->assertNotNull($comm->sent_at);
        $this->assertTrue($comm->metadata['simulated']);
        $this->assertSame('sent', $member->fresh()->status);
    }

    public function test_communication_requires_target_and_rejects_both(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);

        $this->actingAs($user)->post('/communications', [
            'channel' => 'email', 'body' => 'Hola',
        ])->assertSessionHasErrors('contact_id');

        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $this->actingAs($user)->post('/communications', [
            'contact_id' => $contact->id, 'lead_id' => $lead->id, 'channel' => 'email', 'body' => 'Hola',
        ])->assertSessionHasErrors('lead_id');

        $this->assertSame(0, Communication::count());
    }

    public function test_communication_out_of_scope_target_rejected(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $foreignContact = Contact::factory()->create(['owner_id' => $vendorB->id, 'email' => 'oculto@example.com']);

        $this->actingAs($vendorA)->post('/communications', [
            'contact_id' => $foreignContact->id, 'channel' => 'email', 'body' => 'Hola',
        ])->assertForbidden();
        $this->assertSame(0, Communication::count());

        // Comunicación existente con objetivo ajeno no visible ni por show ni por índice.
        $comm = Communication::factory()->create([
            'contact_id' => $foreignContact->id, 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id,
            'subject' => 'Secreta ajena',
        ]);
        $this->actingAs($vendorA)->get("/communications/{$comm->id}")->assertForbidden();
        $this->actingAs($vendorA)->get('/communications')->assertOk()->assertDontSee('Secreta ajena');
    }

    public function test_communication_body_escaped(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $contact = Contact::factory()->create(['owner_id' => $user->id]);
        $comm = Communication::factory()->create([
            'contact_id' => $contact->id, 'owner_id' => $user->id, 'created_by' => $user->id,
            'body' => '<img src=x onerror=alert(1)>',
        ]);

        $this->actingAs($user)->get("/communications/{$comm->id}")->assertOk()
            ->assertSee('&lt;img', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_bulk_communication_creates_and_skips_unsubscribed(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);

        $c1 = Contact::factory()->create(['owner_id' => $user->id]);
        $c2 = Contact::factory()->create(['owner_id' => $user->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $c1->id, 'added_by' => $user->id]);
        CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $c2->id, 'status' => 'unsubscribed', 'added_by' => $user->id]);

        $this->actingAs($user)->post("/campaigns/{$campaign->id}/communications", [
            'channel' => 'email', 'subject' => 'Promo', 'body' => 'Hola {{full_name}}',
        ])->assertRedirect();

        $this->assertSame(1, Communication::count());
        $comm = Communication::firstOrFail();
        $this->assertSame('simulated_sent', $comm->status);
        $this->assertStringNotContainsString('{{', $comm->body);
    }

    public function test_bulk_communication_limit_rejected(): void
    {
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);

        $contacts = Contact::factory(3)->create(['owner_id' => $user->id]);
        foreach ($contacts as $c) {
            CampaignMember::create(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $c->id, 'added_by' => $user->id]);
        }
        $members = $campaign->members()->pluck('id')->all();

        // Simula selección mayor al límite vía member_ids manipulados.
        $big = array_merge($members, range(100000, 100600));
        $this->actingAs($user)->post("/campaigns/{$campaign->id}/communications", [
            'channel' => 'email', 'body' => 'Hola', 'member_ids' => $big,
        ])->assertSessionHasErrors('member_ids');

        $this->assertSame(0, Communication::count());
    }

    public function test_bulk_members_limit_500_rejected(): void
    {
        // Cobertura directa del servicio: >500 aborta sin parciales.
        $user = $this->makeUser($this->marketingPerms(), null, ['Vendedor']);
        $campaign = Campaign::factory()->create(['owner_id' => $user->id, 'created_by' => $user->id]);
        $members = collect();
        for ($i = 0; $i < 501; $i++) {
            $members->push(new CampaignMember(['campaign_id' => $campaign->id, 'member_type' => 'contact', 'member_id' => $i + 1]));
        }

        try {
            CampaignCommunicationService::bulkSimulate($campaign, $members, [
                'channel' => 'email', 'body' => 'Hola',
            ], $user);
            $this->fail('Debió abortar por límite.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, Communication::count());
    }

    // ---------------- IDOR cruzado ----------------

    public function test_idor_vendor_cannot_touch_other_campaign(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        $foreign = Campaign::factory()->create(['name' => 'Ajena IDOR', 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($vendorA)->get("/campaigns/{$foreign->id}")->assertForbidden();
        $this->actingAs($vendorA)->put("/campaigns/{$foreign->id}", $this->campaignPayload(['status' => 'draft']))->assertForbidden();
        $this->actingAs($vendorA)->delete("/campaigns/{$foreign->id}")->assertForbidden();
        $this->assertSame('Ajena IDOR', $foreign->fresh()->name);
    }

    public function test_query_string_does_not_expand_access(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser($this->marketingPerms(), $teamA, ['Vendedor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);
        Campaign::factory()->create(['name' => 'Propia QS', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);
        Campaign::factory()->create(['name' => 'Ajena QS', 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        // ?owner_id=otro no muestra lo ajeno.
        $this->actingAs($vendorA)->get("/campaigns?owner_id={$vendorB->id}")->assertOk()
            ->assertDontSee('Ajena QS')->assertDontSee('Propia QS');

        // ?contact_id fuera de alcance en communications no filtra datos ajenos.
        $foreignContact = Contact::factory()->create(['owner_id' => $vendorB->id]);
        $this->actingAs($vendorA)->post('/communications', [
            'contact_id' => $foreignContact->id, 'channel' => 'email', 'body' => 'x',
            'campaign_id' => null,
        ])->assertForbidden();
    }

    public function test_consulta_read_only(): void
    {
        $consulta = $this->makeUser(
            ['campaigns.view', 'campaigns.create', 'campaigns.update', 'campaigns.delete',
                'templates.view', 'templates.create', 'communications.view', 'communications.create',
                'contacts.view', 'leads.view'],
            null,
            ['Consulta']
        );
        $own = Campaign::factory()->create(['name' => 'Propia consulta', 'owner_id' => $consulta->id, 'created_by' => $consulta->id]);

        $this->actingAs($consulta)->get('/campaigns')->assertOk()->assertSee('Propia consulta');
        $this->actingAs($consulta)->get("/campaigns/{$own->id}")->assertOk();
        $this->actingAs($consulta)->post('/campaigns', $this->campaignPayload())->assertForbidden();
        $this->actingAs($consulta)->put("/campaigns/{$own->id}", $this->campaignPayload(['status' => 'draft']))->assertForbidden();
        $this->actingAs($consulta)->delete("/campaigns/{$own->id}")->assertForbidden();
    }

    public function test_supervisor_same_team_can_view(): void
    {
        $teamA = $this->makeTeam('a');
        $teamB = $this->makeTeam('b');
        $vendorA = $this->makeUser([], $teamA, ['Vendedor']);
        $supervisorA = $this->makeUser($this->marketingPerms(), $teamA, ['Supervisor']);
        $vendorB = $this->makeUser([], $teamB, ['Vendedor']);

        Campaign::factory()->create(['name' => 'Equipo A visible', 'owner_id' => $vendorA->id, 'created_by' => $vendorA->id]);
        Campaign::factory()->create(['name' => 'Equipo B oculta', 'owner_id' => $vendorB->id, 'created_by' => $vendorB->id]);

        $this->actingAs($supervisorA)->get('/campaigns')->assertOk()
            ->assertSee('Equipo A visible')->assertDontSee('Equipo B oculta');
    }
}
