<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Permission;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseNineTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class, TicketCategorySeeder::class]);

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->permissions()->sync(
            Permission::whereIn('name', $permissions)->pluck('id')->all()
        );

        return $user;
    }

    private function ticketAccess(): User
    {
        return $this->userWith(['tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete']);
    }

    private function ticketPayload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Problema con la factura',
            'description' => 'El cliente reporta un cobro duplicado.',
            'requester_name' => 'María López',
            'requester_email' => 'maria@example.com',
            'priority' => 'high',
            'channel' => 'email',
        ], $overrides);
    }

    private function makeTicket(User $owner, array $overrides = []): Ticket
    {
        return Ticket::factory()->create(array_merge([
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
        ], $overrides));
    }

    // ---------------- Correcciones UX pendientes ----------------

    public function test_demo_seeder_grants_superadmin_idempotently(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, TicketCategorySeeder::class]);

        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $user = User::where('email', DemoUserSeeder::EMAIL)->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertSame(1, User::where('email', DemoUserSeeder::EMAIL)->count());
        $this->assertSame(1, $user->roles()->where('name', 'Superadministrador')->count());
    }

    public function test_no_internal_phase_texts_visible(): void
    {
        $user = $this->userWith(['tickets.view', 'users.view']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Fase 2');
        $this->actingAs($user)->get('/teams')->assertOk()->assertDontSee('Fase 2');
    }

    public function test_convert_button_only_on_qualified_leads(): void
    {
        $user = $this->userWith([
            'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
        ]);

        foreach (['new' => false, 'contacted' => false, 'qualified' => true, 'unqualified' => false] as $status => $visible) {
            $lead = \App\Models\Lead::factory()->create(['status' => $status, 'owner_id' => $user->id]);
            $response = $this->actingAs($user)->get("/leads/{$lead->id}")->assertOk();

            if ($visible) {
                $response->assertSee('Convertir', false);
            } else {
                $response->assertDontSee('Convertir', false);
            }
        }
    }

    // ---------------- Tickets CRUD ----------------

    public function test_authorized_user_sees_tickets_list(): void
    {
        $user = $this->ticketAccess();
        $this->makeTicket($user, ['subject' => 'Listado Visible']);

        $this->actingAs($user)->get('/tickets')->assertOk()->assertSee('Listado Visible');
    }

    public function test_user_without_permission_cannot_access_tickets(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class, TicketCategorySeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $ticket = $this->makeTicket(User::factory()->create(['status' => 'active']));

        $this->actingAs($user)->get('/tickets')->assertForbidden();
        $this->actingAs($user)->get('/tickets/create')->assertForbidden();
        $this->actingAs($user)->post('/tickets', $this->ticketPayload())->assertForbidden();
        $this->actingAs($user)->get("/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($user)->get("/tickets/{$ticket->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/tickets/{$ticket->id}", $this->ticketPayload())->assertForbidden();
        $this->actingAs($user)->delete("/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", ['type' => 'reply', 'body' => 'x'])->assertForbidden();
        $this->actingAs($user)->patch("/tickets/{$ticket->id}/open")->assertForbidden();
        $this->actingAs($user)->patch("/tickets/{$ticket->id}/resolve")->assertForbidden();
    }

    public function test_create_ticket_generates_number_and_system_event(): void
    {
        $user = $this->ticketAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        $category = TicketCategory::firstOrFail();

        $response = $this->actingAs($user)->post('/tickets', $this->ticketPayload([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'category_id' => $category->id,
            'assigned_to' => $user->id,
        ]));

        $ticket = Ticket::firstOrFail();
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}$/', $ticket->number);
        $this->assertSame('new', $ticket->status);
        $this->assertSame($user->id, $ticket->created_by);
        $this->assertTrue($ticket->messages()->where('type', 'system')->exists());
    }

    public function test_create_ticket_validation(): void
    {
        $user = $this->ticketAccess();

        // Sin asunto, sin solicitante y con valores inválidos.
        $response = $this->actingAs($user)->from('/tickets/create')->post('/tickets', $this->ticketPayload([
            'subject' => '',
            'requester_name' => '',
            'requester_email' => '',
            'priority' => 'extrema',
            'channel' => 'humo',
        ]));

        $response->assertRedirect('/tickets/create');
        $response->assertSessionHasErrors(['subject', 'requester_name', 'priority', 'channel']);
        $this->assertSame(0, Ticket::count());
    }

    public function test_contact_from_other_company_rejected(): void
    {
        $user = $this->ticketAccess();
        $companyA = Company::factory()->create(['owner_id' => $user->id]);
        $companyB = Company::factory()->create(['owner_id' => $user->id]);
        $contactB = Contact::factory()->create(['company_id' => $companyB->id, 'owner_id' => $user->id]);

        $response = $this->actingAs($user)->from('/tickets/create')->post('/tickets', $this->ticketPayload([
            'company_id' => $companyA->id,
            'contact_id' => $contactB->id,
        ]));

        $response->assertRedirect('/tickets/create');
        $response->assertSessionHasErrors('contact_id');
        $this->assertSame(0, Ticket::count());
    }

    public function test_update_ticket_logs_assignee_change(): void
    {
        $user = $this->ticketAccess();
        $other = User::factory()->create(['status' => 'active']);
        $ticket = $this->makeTicket($user, ['assigned_to' => null]);

        $response = $this->actingAs($user)->put("/tickets/{$ticket->id}", $this->ticketPayload([
            'assigned_to' => $other->id,
        ]));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame($other->id, $ticket->fresh()->assigned_to);
        $this->assertTrue(
            $ticket->messages()->where('type', 'system')->where('body', 'like', '%Responsable cambiado%')->exists()
        );
    }

    public function test_delete_ticket_soft_deletes_and_keeps_messages(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user);
        $ticket->messages()->create(['user_id' => $user->id, 'type' => 'reply', 'body' => 'Hola', 'is_internal' => false]);

        $this->actingAs($user)->delete("/tickets/{$ticket->id}")
            ->assertRedirect(route('tickets.index'));
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id]);
    }

    public function test_search_filter_sort_paginate_tickets(): void
    {
        $user = $this->ticketAccess();
        $company = Company::factory()->create(['trade_name' => 'BuscadaT S.A.S.', 'owner_id' => $user->id]);
        $category = TicketCategory::firstOrFail();
        $this->makeTicket($user, [
            'subject' => 'BuscadoTXYZ', 'company_id' => $company->id, 'status' => 'open',
            'priority' => 'urgent', 'category_id' => $category->id, 'channel' => 'web',
        ]);
        $this->makeTicket($user, ['subject' => 'Descartado', 'status' => 'new', 'priority' => 'low', 'channel' => 'phone']);

        $response = $this->actingAs($user)->get('/tickets?search=buscadotxyz');
        $response->assertOk()->assertSee('BuscadoTXYZ')->assertDontSee('Descartado');

        $response = $this->actingAs($user)->get(
            "/tickets?status=open&priority=urgent&category_id={$category->id}"
            ."&assigned_to={$user->id}&company_id={$company->id}&channel=web"
        );
        $response->assertOk()->assertSee('BuscadoTXYZ')->assertDontSee('Descartado');

        foreach (['mine' => 'BuscadoTXYZ', 'unassigned' => null, 'open' => 'BuscadoTXYZ', 'pending' => null, 'urgent' => 'BuscadoTXYZ'] as $preset => $expected) {
            $response = $this->actingAs($user)->get("/tickets?preset={$preset}");
            $response->assertOk();
            if ($expected) {
                $response->assertSee($expected);
            }
        }

        $response = $this->actingAs($user)->get('/tickets?sort=last_reply_at&direction=desc');
        $response->assertOk();

        Ticket::query()->delete();
        for ($i = 0; $i < 16; $i++) {
            $this->makeTicket($user, ['subject' => "Masivo {$i}"]);
        }
        $this->assertCount(15, $this->actingAs($user)->get('/tickets')->viewData('tickets')->items());
        $this->assertCount(1, $this->actingAs($user)->get('/tickets?page=2')->viewData('tickets')->items());
    }

    // ---------------- Conversación ----------------

    public function test_first_reply_sets_response_clocks(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);

        $response = $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", [
            'type' => 'reply', 'body' => 'Estamos revisando tu caso.',
        ]);

        $response->assertRedirect();
        $ticket = $ticket->fresh();
        $this->assertNotNull($ticket->first_response_at);
        $this->assertNotNull($ticket->last_reply_at);
        $message = $ticket->messages()->where('type', 'reply')->firstOrFail();
        $this->assertFalse((bool) $message->is_internal);
    }

    public function test_second_reply_keeps_first_response_but_updates_last(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);
        $first = now()->subHour();
        $ticket->update(['first_response_at' => $first, 'last_reply_at' => $first]);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", [
            'type' => 'reply', 'body' => 'Seguimiento.',
        ])->assertRedirect();

        $ticket = $ticket->fresh();
        $this->assertSame($first->format('Y-m-d H:i:s'), $ticket->first_response_at->format('Y-m-d H:i:s'));
        $this->assertTrue($ticket->last_reply_at->gt($first));
    }

    public function test_internal_note_does_not_move_clocks(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", [
            'type' => 'note', 'body' => 'Llamar al proveedor.',
        ])->assertRedirect();

        $ticket = $ticket->fresh();
        $message = $ticket->messages()->where('type', 'note')->firstOrFail();
        $this->assertTrue((bool) $message->is_internal);
        $this->assertNull($ticket->first_response_at);
        $this->assertNull($ticket->last_reply_at);
    }

    public function test_closed_ticket_rejects_new_messages(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'closed']);

        $response = $this->actingAs($user)->from("/tickets/{$ticket->id}")
            ->post("/tickets/{$ticket->id}/messages", ['type' => 'reply', 'body' => 'Hola?']);

        $response->assertRedirect("/tickets/{$ticket->id}");
        $response->assertSessionHas('error');
        $this->assertSame(0, $ticket->messages()->where('type', 'reply')->count());
    }

    public function test_system_type_rejected_from_frontend(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);

        $response = $this->actingAs($user)->from("/tickets/{$ticket->id}")
            ->post("/tickets/{$ticket->id}/messages", ['type' => 'system', 'body' => 'Hackeo']);

        $response->assertRedirect("/tickets/{$ticket->id}");
        $response->assertSessionHasErrors('type');
        $this->assertSame(0, $ticket->messages()->where('body', 'Hackeo')->count());
    }

    public function test_message_body_is_escaped(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", [
            'type' => 'reply', 'body' => '<script>alert("x")</script>',
        ])->assertRedirect();

        $this->actingAs($user)->get("/tickets/{$ticket->id}")
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
    }

    // ---------------- Estados ----------------

    public function test_full_transition_flow_with_events_and_timestamps(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'new']);

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/open")->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/pending")->assertRedirect();
        $this->assertSame('pending', $ticket->fresh()->status);

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/open")->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/resolve")->assertRedirect();
        $ticket = $ticket->fresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/close")->assertRedirect();
        $ticket = $ticket->fresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        // Reapertura limpia los relojes.
        $this->actingAs($user)->patch("/tickets/{$ticket->id}/reopen")->assertRedirect();
        $ticket = $ticket->fresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);

        // Un evento system por cambio real (6) + ninguno extra por no-op.
        $this->assertSame(6, $ticket->messages()->where('type', 'system')->count());
    }

    public function test_invalid_transitions_rejected_without_changes(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'new']);
        $eventsBefore = $ticket->messages()->where('type', 'system')->count();

        // new → closed no existe; open → closed tampoco (solo resolved cierra).
        $this->actingAs($user)->from("/tickets/{$ticket->id}")
            ->patch("/tickets/{$ticket->id}/close")->assertSessionHasErrors('status');
        $this->assertSame('new', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
        $this->assertSame($eventsBefore, $ticket->messages()->where('type', 'system')->count());

        $ticket->update(['status' => 'resolved']);
        $this->actingAs($user)->from("/tickets/{$ticket->id}")
            ->patch("/tickets/{$ticket->id}/pending")->assertSessionHasErrors('status');
        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_same_status_is_noop_without_event(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, ['status' => 'open']);
        $eventsBefore = $ticket->messages()->where('type', 'system')->count();

        $this->actingAs($user)->patch("/tickets/{$ticket->id}/open")->assertRedirect();
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertSame($eventsBefore, $ticket->messages()->where('type', 'system')->count());
    }

    public function test_metrics_are_deterministic(): void
    {
        $user = $this->ticketAccess();
        $ticket = $this->makeTicket($user, [
            'status' => 'open',
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $this->actingAs($user)->post("/tickets/{$ticket->id}/messages", [
            'type' => 'reply', 'body' => 'Respuesta.',
        ])->assertRedirect();

        $ticket = $ticket->fresh();
        // Tolerancia ±1s por truncado a segundos en SQLite.
        $this->assertEqualsWithDelta(3 * 3600, $ticket->first_response_seconds, 1);

        $ticket->forceFill(['created_at' => now()->subHours(5)])->save();
        $this->actingAs($user)->patch("/tickets/{$ticket->id}/resolve")->assertRedirect();
        $this->assertEqualsWithDelta(5 * 3600, $ticket->fresh()->resolution_seconds, 1);
    }

    // ---------------- Integraciones ----------------

    public function test_company_and_contact_show_tickets(): void
    {
        $user = $this->userWith([
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete',
            'companies.view', 'contacts.view',
        ]);
        $company = Company::factory()->create(['trade_name' => 'Soporte S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['company_id' => $company->id, 'owner_id' => $user->id]);
        $ticket = $this->makeTicket($user, [
            'subject' => 'Caso Integrado', 'company_id' => $company->id, 'contact_id' => $contact->id,
        ]);

        $this->actingAs($user)->get("/companies/{$company->id}")
            ->assertOk()->assertSee($ticket->number)->assertSee('Nuevo ticket');
        $this->actingAs($user)->get("/contacts/{$contact->id}")
            ->assertOk()->assertSee($ticket->number);
    }

    public function test_dashboard_shows_support_widgets(): void
    {
        $user = $this->userWith(['tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete']);
        $this->makeTicket($user, ['subject' => 'Abierto Uno', 'status' => 'open', 'priority' => 'urgent']);
        $this->makeTicket($user, ['subject' => 'Otro Caso', 'status' => 'new', 'priority' => 'low', 'assigned_to' => null]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('Soporte abierto')
            ->assertSee('Abierto Uno')
            ->assertSee('Sin asignar');
    }
}
