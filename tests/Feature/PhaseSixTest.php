<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseSixTest extends TestCase
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

    private function taskAccess(): User
    {
        return $this->userWith(['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete']);
    }

    private function activityAccess(): User
    {
        return $this->userWith([
            'activities.view', 'activities.create', 'activities.update', 'activities.delete',
        ]);
    }

    private function fullAccess(): User
    {
        return $this->userWith([
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'activities.view', 'activities.create', 'activities.update', 'activities.delete',
            'companies.view', 'contacts.view', 'leads.view', 'opportunities.view',
        ]);
    }

    private function makeTask(User $owner, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'title' => 'Tarea de prueba',
            'description' => 'Descripción.',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
        ], $overrides));
    }

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Llamar al cliente',
            'description' => 'Seguimiento.',
            'status' => 'pending',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    private function activityPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'call',
            'subject' => 'Llamada de seguimiento',
            'description' => 'Cliente interesado.',
            'status' => 'completed',
        ], $overrides);
    }

    // ---------------- Tasks CRUD ----------------

    public function test_authorized_user_sees_tasks_list(): void
    {
        $user = $this->taskAccess();
        $this->makeTask($user, ['title' => 'Listada Visible']);

        $this->actingAs($user)->get('/tasks')->assertOk()->assertSee('Listada Visible');
    }

    public function test_user_without_permission_cannot_access_tasks(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $task = $this->makeTask(User::factory()->create(['status' => 'active']));

        $this->actingAs($user)->get('/tasks')->assertForbidden();
        $this->actingAs($user)->get('/tasks/create')->assertForbidden();
        $this->actingAs($user)->post('/tasks', $this->taskPayload())->assertForbidden();
        $this->actingAs($user)->get("/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($user)->get("/tasks/{$task->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/tasks/{$task->id}", $this->taskPayload())->assertForbidden();
        $this->actingAs($user)->delete("/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($user)->patch("/tasks/{$task->id}/complete")->assertForbidden();
        $this->actingAs($user)->patch("/tasks/{$task->id}/reopen")->assertForbidden();
        $this->actingAs($user)->patch("/tasks/{$task->id}/cancel")->assertForbidden();
    }

    public function test_create_task_with_related_company(): void
    {
        $user = $this->taskAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/tasks', $this->taskPayload([
            'assigned_to' => $user->id,
            'related_type' => 'company',
            'related_id' => $company->id,
        ]));

        $task = Task::where('title', 'Llamar al cliente')->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));
        $response->assertSessionHas('success', 'Tarea creada correctamente.');
        $this->assertSame(Company::class, $task->taskable_type);
        $this->assertSame($company->id, $task->taskable_id);
        $this->assertSame($user->id, $task->created_by);
    }

    public function test_create_task_rejects_arbitrary_morph_type(): void
    {
        $user = $this->taskAccess();

        $response = $this->actingAs($user)->from('/tasks/create')->post('/tasks', $this->taskPayload([
            'related_type' => 'App\\Models\\User',
            'related_id' => $user->id,
        ]));

        $response->assertRedirect('/tasks/create');
        $response->assertSessionHasErrors('related_type');
        $this->assertSame(0, Task::count());
    }

    public function test_create_task_validation(): void
    {
        $user = $this->taskAccess();

        $response = $this->actingAs($user)->from('/tasks/create')->post('/tasks', $this->taskPayload([
            'title' => '',
            'priority' => 'extrema',
            'status' => 'completed',
        ]));

        $response->assertRedirect('/tasks/create');
        $response->assertSessionHasErrors(['title', 'priority', 'status']);
    }

    public function test_show_and_update_task(): void
    {
        $user = $this->taskAccess();
        $task = $this->makeTask($user, ['title' => 'Visible Una']);

        $this->actingAs($user)->get("/tasks/{$task->id}")->assertOk()->assertSee('Visible Una');

        $response = $this->actingAs($user)->put("/tasks/{$task->id}", $this->taskPayload([
            'title' => 'Actualizada Una',
            'status' => 'in_progress',
            'assigned_to' => null,
        ]));

        $response->assertRedirect(route('tasks.show', $task));
        $task = $task->fresh();
        $this->assertSame('Actualizada Una', $task->title);
        $this->assertSame('in_progress', $task->status);
        $this->assertNull($task->assigned_to);
    }

    public function test_delete_task_soft_deletes(): void
    {
        $user = $this->taskAccess();
        $task = $this->makeTask($user);

        $response = $this->actingAs($user)->delete("/tasks/{$task->id}");

        $response->assertRedirect(route('tasks.index'));
        $response->assertSessionHas('success', 'Tarea eliminada correctamente.');
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_search_and_filter_tasks(): void
    {
        $user = $this->taskAccess();
        $other = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $this->makeTask($user, [
            'title' => 'FiltradaXYZ', 'status' => 'pending', 'priority' => 'high',
            'due_at' => now()->addDay(), 'taskable_type' => Company::class, 'taskable_id' => $company->id,
        ]);
        $this->makeTask($other, ['title' => 'Descartada', 'status' => 'completed', 'priority' => 'low', 'assigned_to' => $other->id]);

        $response = $this->actingAs($user)->get('/tasks?search=filtradaxyz');
        $response->assertOk();
        $response->assertSee('FiltradaXYZ');
        $response->assertDontSee('Descartada');

        $response = $this->actingAs($user)->get(
            "/tasks?status=pending&priority=high&assigned_to={$user->id}&related_type=company&due=upcoming"
        );
        $response->assertOk();
        $response->assertSee('FiltradaXYZ');
        $response->assertDontSee('Descartada');
    }

    public function test_sort_and_paginate_tasks(): void
    {
        $user = $this->taskAccess();
        $this->makeTask($user, ['due_at' => now()->addDays(5), 'priority' => 'low']);
        $this->makeTask($user, ['due_at' => now()->addDay(), 'priority' => 'urgent']);

        $response = $this->actingAs($user)->get('/tasks?sort=due_at&direction=asc');
        $response->assertOk();
        $items = $response->viewData('tasks')->items();
        $this->assertTrue($items[0]->due_at->lte($items[1]->due_at));

        Task::query()->delete();
        for ($i = 0; $i < 16; $i++) {
            $this->makeTask($user, ['title' => "Masiva {$i}"]);
        }
        $pageOne = $this->actingAs($user)->get('/tasks');
        $this->assertCount(15, $pageOne->viewData('tasks')->items());
        $pageTwo = $this->actingAs($user)->get('/tasks?page=2');
        $this->assertCount(1, $pageTwo->viewData('tasks')->items());
    }

    public function test_contextual_create_preselects_related(): void
    {
        $user = $this->taskAccess();
        $company = Company::factory()->create(['trade_name' => 'Preseleccionada S.A.S.', 'owner_id' => $user->id]);

        $this->actingAs($user)->get("/tasks/create?related=company:{$company->id}")
            ->assertOk()
            ->assertSee('Preseleccionada S.A.S.');
    }

    // ---------------- Complete / reopen / cancel ----------------

    public function test_complete_task_sets_status_and_date(): void
    {
        $user = $this->taskAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $task = $this->makeTask($user, [
            'taskable_type' => Company::class, 'taskable_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->patch("/tasks/{$task->id}/complete");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tarea completada correctamente.');
        $task = $task->fresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        // Actividad registrada en la entidad relacionada.
        $this->assertTrue(
            $company->activities()->where('subject', 'like', 'Tarea completada%')->exists()
        );
    }

    public function test_cannot_complete_cancelled_task(): void
    {
        $user = $this->taskAccess();
        $task = $this->makeTask($user, ['status' => 'cancelled']);

        $this->actingAs($user)->patch("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertSame('cancelled', $task->fresh()->status);
    }

    public function test_reopen_task_resets_status_and_date(): void
    {
        $user = $this->taskAccess();
        $task = $this->makeTask($user, ['status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($user)->patch("/tasks/{$task->id}/reopen");

        $response->assertRedirect();
        $task = $task->fresh();
        $this->assertSame('pending', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_cancel_task_keeps_completed_at_null(): void
    {
        $user = $this->taskAccess();
        $task = $this->makeTask($user, ['status' => 'in_progress']);

        $response = $this->actingAs($user)->patch("/tasks/{$task->id}/cancel");

        $response->assertRedirect();
        $task = $task->fresh();
        $this->assertSame('cancelled', $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_overdue_logic(): void
    {
        $user = $this->taskAccess();

        $pastPending = $this->makeTask($user, ['due_at' => now()->subDay(), 'status' => 'pending']);
        $pastDone = $this->makeTask($user, ['due_at' => now()->subDay(), 'status' => 'completed', 'completed_at' => now()]);
        $future = $this->makeTask($user, ['due_at' => now()->addDay(), 'status' => 'pending']);
        $noDue = $this->makeTask($user, ['due_at' => null]);

        $this->assertTrue($pastPending->is_overdue);
        $this->assertFalse($pastDone->is_overdue);
        $this->assertFalse($future->is_overdue);
        $this->assertFalse($noDue->is_overdue);
    }

    // ---------------- Activities ----------------

    public function test_authorized_user_sees_activities_list(): void
    {
        $user = $this->activityAccess();
        Activity::create([
            'type' => 'note', 'subject' => 'Nota Visible', 'status' => 'completed', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->get('/activities')->assertOk()->assertSee('Nota Visible');
    }

    public function test_user_without_permission_cannot_access_activities(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);
        $activity = Activity::create([
            'type' => 'note', 'subject' => 'X', 'status' => 'pending',
            'user_id' => User::factory()->create(['status' => 'active'])->id,
        ]);

        $this->actingAs($user)->get('/activities')->assertForbidden();
        $this->actingAs($user)->get('/activities/create')->assertForbidden();
        $this->actingAs($user)->post('/activities', $this->activityPayload())->assertForbidden();
        $this->actingAs($user)->get("/activities/{$activity->id}")->assertForbidden();
        $this->actingAs($user)->get("/activities/{$activity->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/activities/{$activity->id}", $this->activityPayload())->assertForbidden();
        $this->actingAs($user)->delete("/activities/{$activity->id}")->assertForbidden();
    }

    public function test_create_manual_activity_with_related(): void
    {
        $user = $this->activityAccess();
        $contact = Contact::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/activities', $this->activityPayload([
            'type' => 'meeting',
            'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'related_type' => 'contact',
            'related_id' => $contact->id,
        ]));

        $activity = Activity::where('subject', 'Llamada de seguimiento')->firstOrFail();
        $response->assertRedirect(route('activities.show', $activity));
        $this->assertSame(Contact::class, $activity->subjectable_type);
        $this->assertSame($contact->id, $activity->subjectable_id);
        $this->assertSame($user->id, $activity->user_id);
    }

    public function test_create_activity_rejects_system_type(): void
    {
        $user = $this->activityAccess();

        $response = $this->actingAs($user)->from('/activities/create')->post('/activities', $this->activityPayload([
            'type' => 'status_change',
        ]));

        $response->assertRedirect('/activities/create');
        $response->assertSessionHasErrors('type');
        $this->assertSame(0, Activity::count());
    }

    public function test_meeting_requires_scheduled_at(): void
    {
        $user = $this->activityAccess();

        $response = $this->actingAs($user)->from('/activities/create')->post('/activities', $this->activityPayload([
            'type' => 'meeting',
            'scheduled_at' => null,
        ]));

        $response->assertSessionHasErrors('scheduled_at');
    }

    public function test_system_activity_cannot_be_edited_or_deleted(): void
    {
        $user = $this->activityAccess();
        $lead = Lead::factory()->create(['owner_id' => $user->id]);
        $system = $lead->activities()->create([
            'type' => 'status_change', 'subject' => 'Lead convertido',
            'status' => 'completed', 'user_id' => $user->id,
        ]);

        // Página de edición redirige con error; update directo 403.
        $this->actingAs($user)->get("/activities/{$system->id}/edit")->assertRedirect();
        $this->actingAs($user)->put("/activities/{$system->id}", $this->activityPayload())->assertForbidden();
        $this->actingAs($user)->delete("/activities/{$system->id}")->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $system->id, 'deleted_at' => null]);
    }

    public function test_update_and_delete_manual_activity(): void
    {
        $user = $this->activityAccess();
        $activity = Activity::create([
            'type' => 'note', 'subject' => 'Editable', 'status' => 'pending', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)->put("/activities/{$activity->id}", $this->activityPayload([
            'type' => 'email', 'subject' => 'Editada', 'status' => 'completed',
        ]))->assertRedirect(route('activities.show', $activity));
        $this->assertSame('Editada', $activity->fresh()->subject);
        $this->assertNotNull($activity->fresh()->completed_at);

        $this->actingAs($user)->delete("/activities/{$activity->id}")
            ->assertRedirect(route('activities.index'));
        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }

    public function test_search_and_filter_activities(): void
    {
        $user = $this->activityAccess();
        $other = User::factory()->create(['status' => 'active']);
        $company = Company::factory()->create(['owner_id' => $user->id]);
        Activity::create([
            'type' => 'call', 'subject' => 'FiltradaXYZ', 'status' => 'completed',
            'user_id' => $user->id, 'subjectable_type' => Company::class,
            'subjectable_id' => $company->id, 'scheduled_at' => now()->addDay(),
        ]);
        Activity::create([
            'type' => 'note', 'subject' => 'Descartada', 'status' => 'pending', 'user_id' => $other->id,
        ]);

        $response = $this->actingAs($user)->get('/activities?search=filtradaxyz');
        $response->assertOk();
        $response->assertSee('FiltradaXYZ');
        $response->assertDontSee('Descartada');

        $response = $this->actingAs($user)->get(
            "/activities?type=call&user_id={$user->id}&related_type=company"
            .'&from='.now()->format('Y-m-d').'&to='.now()->addDays(3)->format('Y-m-d')
        );
        $response->assertOk();
        $response->assertSee('FiltradaXYZ');
        $response->assertDontSee('Descartada');
    }

    public function test_related_labels_for_all_entities(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['trade_name' => 'Etiqueta S.A.S.', 'owner_id' => $user->id]);
        $contact = Contact::factory()->create(['first_name' => 'Eti', 'last_name' => 'Queta', 'company_id' => $company->id, 'owner_id' => $user->id]);
        $lead = Lead::factory()->create(['first_name' => 'Li', 'last_name' => 'Ad', 'owner_id' => $user->id]);
        $pipeline = Pipeline::where('name', 'Ventas')->firstOrFail();
        $stage = $pipeline->stages()->orderBy('position')->firstOrFail();
        $opp = Opportunity::factory()->create([
            'name' => 'Opp Etiqueta', 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id,
            'company_id' => $company->id, 'owner_id' => $user->id,
        ]);

        foreach ([
            [$company, 'Etiqueta S.A.S.', 'companies.show'],
            [$contact, 'Eti Queta', 'contacts.show'],
            [$lead, 'Li Ad', 'leads.show'],
            [$opp, 'Opp Etiqueta', 'opportunities.show'],
        ] as [$model, $label, $route]) {
            $task = $this->makeTask($user, [
                'taskable_type' => $model::class, 'taskable_id' => $model->id,
            ]);
            $this->assertSame($label, $task->related_label);
            $this->assertSame(route($route, $model), $task->related_url);
        }
    }

    public function test_company_timeline_shows_activity_and_task(): void
    {
        $user = $this->fullAccess();
        $company = Company::factory()->create(['owner_id' => $user->id]);
        $company->activities()->create([
            'type' => 'call', 'subject' => 'Timeline Llamada', 'status' => 'completed', 'user_id' => $user->id,
        ]);
        $this->makeTask($user, [
            'title' => 'Timeline Tarea', 'taskable_type' => Company::class, 'taskable_id' => $company->id,
        ]);

        $this->actingAs($user)->get("/companies/{$company->id}")
            ->assertOk()
            ->assertSee('Timeline Llamada')
            ->assertSee('Timeline Tarea')
            ->assertSee('Timeline');
    }

    // ---------------- Calendario ----------------

    public function test_calendar_requires_permission(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, PipelineSeeder::class]);
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/calendar')->assertForbidden();
    }

    public function test_calendar_shows_task_and_meeting_events(): void
    {
        $user = $this->fullAccess();
        $due = now()->addDays(2)->setTime(10, 30);
        $this->makeTask($user, ['title' => 'Tarea Calendario', 'due_at' => $due]);
        Activity::create([
            'type' => 'meeting', 'subject' => 'Reunión Calendario', 'status' => 'pending',
            'scheduled_at' => $due->copy()->addDay(), 'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/calendar?view=month&date='.$due->format('Y-m-d'));

        $response->assertOk();
        $response->assertSee('Tarea Calendario');
        $response->assertSee('Reunión Calendario');

        $events = $response->viewData('events');
        $this->assertArrayHasKey($due->format('Y-m-d'), $events);
        $this->assertSame(
            route('tasks.show', Task::where('title', 'Tarea Calendario')->firstOrFail()),
            $events[$due->format('Y-m-d')][0]['url']
        );
    }

    public function test_calendar_week_and_day_views(): void
    {
        $user = $this->fullAccess();

        $this->actingAs($user)->get('/calendar?view=week')->assertOk();
        $this->actingAs($user)->get('/calendar?view=day')->assertOk();
    }

    public function test_calendar_scoped_by_permission(): void
    {
        $taskUser = $this->userWith(['tasks.view']);
        $this->makeTask($taskUser, ['title' => 'Solo Tarea', 'due_at' => now()->addDay()]);

        // Sin activities.view: no ve reuniones pero sí tareas.
        $response = $this->actingAs($taskUser)->get('/calendar');
        $response->assertOk();
        $response->assertSee('Solo Tarea');
    }

    // ---------------- Dashboard ----------------

    public function test_dashboard_shows_task_widgets(): void
    {
        $user = $this->fullAccess();
        $this->makeTask($user, ['title' => 'Hoy Uno', 'due_at' => now()->setTime(15, 0)]);
        $this->makeTask($user, ['title' => 'Vencida Una', 'due_at' => now()->subDay()]);
        $this->makeTask($user, ['title' => 'Próxima Una', 'due_at' => now()->addDays(3)]);
        Activity::create([
            'type' => 'meeting', 'subject' => 'Reunión Próxima', 'status' => 'pending',
            'scheduled_at' => now()->addDays(2), 'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Hoy Uno');
        $response->assertSee('Vencida Una');
        $response->assertSee('Próxima Una');
        $response->assertSee('Reunión Próxima');
    }
}
