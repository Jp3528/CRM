<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\PrivateDocument;
use App\Models\Role;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Notifications\InternalNotificationService;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 16 — Auditoría, Avisos y Documentos Privados.
 * P16A: Servicio de auditoría, sanitización estricta y visor administrativo protegido.
 * P16B: Centro de notificaciones internas, deduplicación y recordatorios programables.
 * P16C: Adjuntos polimórficos privados, validación de contenido, prevención IDOR y descargas seguras.
 */
class PhaseSixteenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, SettingSeeder::class]);
        Storage::fake('private');
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
    // P16A: Auditoría
    // =========================================================================

    public function test_superadmin_can_access_audit_logs_and_filter(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        // Registrar una acción de prueba
        app(AuditService::class)->logAction(
            actor: $super,
            action: 'test.action',
            entityType: 'User',
            entityId: $super->id,
            oldValues: ['name' => 'Antiguo'],
            newValues: ['name' => 'Nuevo']
        );

        $response = $this->actingAs($super)->get(route('audit.index'));
        $response->assertOk();
        $response->assertSee('Registro de Auditoría');
        $response->assertSee('test.action');
        $response->assertSee($super->name);
    }

    public function test_commercial_user_cannot_access_audit_logs_via_direct_url(): void
    {
        $vendedor = $this->makeUser(['Vendedor'], ['companies.view', 'audit.view']);

        $response = $this->actingAs($vendedor)->get(route('audit.index'));
        $response->assertForbidden();
    }

    public function test_audit_service_sanitizes_passwords_and_sensitive_tokens(): void
    {
        $admin = $this->makeUser(['Administrador'], ['audit.view']);

        $auditService = app(AuditService::class);
        $log = $auditService->logAction(
            actor: $admin,
            action: 'security.check',
            entityType: 'User',
            entityId: 999,
            oldValues: [
                'password' => 'secret123',
                'remember_token' => 'tokenABC',
                'api_key' => 'keyXYZ',
                'name' => 'Nombre Antiguo',
            ],
            newValues: [
                'password' => 'newSecret456',
                'app_key' => 'base64:...',
                'name' => 'Nombre Nuevo',
                'email' => 'seguro@ejemplo.com',
            ]
        );

        $this->assertArrayNotHasKey('password', $log->old_values ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->old_values ?? []);
        $this->assertArrayNotHasKey('api_key', $log->old_values ?? []);
        $this->assertEquals('Nombre Antiguo', $log->old_values['name'] ?? null);

        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('app_key', $log->new_values ?? []);
        $this->assertEquals('Nombre Nuevo', $log->new_values['name'] ?? null);
        $this->assertEquals('seguro@ejemplo.com', $log->new_values['email'] ?? null);
    }

    public function test_user_creation_records_sanitized_audit_log(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Nuevo Agente',
            'email' => 'nuevo.agente@empresa.com',
            'password' => 'Password123#Secure',
            'status' => 'active',
        ]);

        $response->assertRedirect();

        $log = AuditLog::where('action', 'user.created')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('Nuevo Agente', $log->new_values['name']);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    public function test_rolled_back_transaction_does_not_persist_audit_log(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $initialCount = AuditLog::count();

        try {
            DB::transaction(function () use ($super) {
                app(AuditService::class)->logAction(
                    actor: $super,
                    action: 'transaction.fail_test',
                    entityType: 'User',
                    entityId: $super->id,
                    oldValues: null,
                    newValues: ['temp' => 'value']
                );

                throw new \RuntimeException('Fallo simulado para forzar rollback');
            });
        } catch (\RuntimeException $e) {
            // Rollback esperado
        }

        $this->assertEquals($initialCount, AuditLog::count());
    }

    // =========================================================================
    // P16B: Notificaciones Internas
    // =========================================================================

    public function test_task_assignment_sends_notification_to_active_user(): void
    {
        $team = Team::factory()->create();
        $creator = $this->makeUser(['Supervisor'], ['tasks.create', 'tasks.view'], $team);
        $assignee = $this->makeUser(['Vendedor'], ['tasks.view'], $team);

        $response = $this->actingAs($creator)->post(route('tasks.store'), [
            'title' => 'Llamar al cliente potencial',
            'priority' => 'high',
            'status' => 'pending',
            'assigned_to' => $assignee->id,
        ]);

        $response->assertRedirect();

        $this->assertEquals(1, $assignee->notifications()->count());
        $notif = $assignee->notifications()->first();
        $this->assertEquals('task.assigned', $notif->data['action']);
        $this->assertStringContainsString('Llamar al cliente potencial', $notif->data['message']);
    }

    public function test_inactive_user_does_not_receive_notifications(): void
    {
        $team = Team::factory()->create();
        $creator = $this->makeUser(['Supervisor'], ['tasks.create', 'tasks.view'], $team);
        $inactiveAssignee = $this->makeUser(['Vendedor'], ['tasks.view'], $team, 'inactive');

        $this->actingAs($creator)->post(route('tasks.store'), [
            'title' => 'Tarea a usuario inactivo',
            'priority' => 'medium',
            'status' => 'pending',
            'assigned_to' => $inactiveAssignee->id,
        ]);

        $this->assertEquals(0, $inactiveAssignee->notifications()->count());
    }

    public function test_user_cannot_read_or_mark_another_users_notification(): void
    {
        $user1 = $this->makeUser(['Vendedor'], ['tasks.view']);
        $user2 = $this->makeUser(['Vendedor'], ['tasks.view']);

        $task = Task::create([
            'title' => 'Tarea de prueba',
            'assigned_to' => $user1->id,
            'created_by' => $user1->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        app(InternalNotificationService::class)->sendTaskAssigned($task);

        $notif1 = $user1->notifications()->firstOrFail();

        // user2 intenta marcar la notificación de user1
        $response = $this->actingAs($user2)->post(route('notifications.read', $notif1->id));
        $response->assertNotFound();

        // La notificación sigue sin leerse
        $this->assertNull($notif1->fresh()->read_at);
    }

    public function test_remind_tasks_command_generates_notifications_and_deduplicates(): void
    {
        $user = $this->makeUser(['Vendedor'], ['tasks.view']);

        $overdueTask = Task::create([
            'title' => 'Tarea Vencida de Prueba',
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'status' => 'pending',
            'priority' => 'high',
            'due_at' => now()->subDays(2),
        ]);

        // Primera ejecución del comando
        $this->artisan('crm:remind-tasks', ['--date' => now()->format('Y-m-d')])
            ->assertSuccessful();

        $this->assertEquals(1, $user->notifications()->count());
        $notif = $user->notifications()->first();
        $this->assertEquals('task.reminder.overdue', $notif->data['action']);

        // Segunda ejecución en el mismo día/periodo: DEDUPLICACIÓN
        $this->artisan('crm:remind-tasks', ['--date' => now()->format('Y-m-d')])
            ->assertSuccessful();

        // No debe haberse multiplicado
        $this->assertEquals(1, $user->notifications()->count());
    }

    // =========================================================================
    // P16C: Documentos Privados
    // =========================================================================

    public function test_authorized_user_can_upload_private_document_to_company(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'companies.update', 'documents.create', 'documents.view']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $file = UploadedFile::fake()->create('propuesta_comercial.pdf', 500, 'application/pdf');

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'documentable_type' => 'company',
            'documentable_id' => $company->id,
            'file' => $file,
            'description' => 'Propuesta aprobada por gerencia',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertEquals(1, $company->documents()->count());
        $doc = $company->documents()->first();

        $this->assertEquals('propuesta_comercial.pdf', $doc->original_name);
        $this->assertEquals('application/pdf', $doc->mime_type);
        $this->assertTrue(Storage::disk('private')->exists($doc->file_path));

        // Verificar que se registró auditoría
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.uploaded',
            'entity_id' => $doc->id,
        ]);
    }

    public function test_private_document_download_requires_current_authorization_idor(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $userA = $this->makeUser(['Vendedor'], ['companies.view', 'companies.update', 'documents.create', 'documents.view'], $teamA);
        $userB = $this->makeUser(['Vendedor'], ['companies.view', 'documents.view'], $teamB);

        $companyA = Company::factory()->create(['owner_id' => $userA->id]);

        // Cargar documento en companyA
        $file = UploadedFile::fake()->create('acuerdo_confidencial.pdf', 300, 'application/pdf');
        $this->actingAs($userA)->post(route('documents.store'), [
            'documentable_type' => 'company',
            'documentable_id' => $companyA->id,
            'file' => $file,
        ]);

        $doc = $companyA->documents()->firstOrFail();

        // userA puede descargar
        $resA = $this->actingAs($userA)->get(route('documents.download', $doc));
        $resA->assertOk();

        // userB de otro equipo intenta descargar (IDOR) -> 403 Forbidden
        $resB = $this->actingAs($userB)->get(route('documents.download', $doc));
        $resB->assertForbidden();
    }

    public function test_malicious_script_file_is_rejected_without_leaving_orphan_file(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'companies.update', 'documents.create']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Archivo que contiene PHP camuflado
        $maliciousContent = "<?php echo 'malicious payload'; ?>";
        $file = UploadedFile::fake()->createWithContent('malware.pdf', $maliciousContent);

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'documentable_type' => 'company',
            'documentable_id' => $company->id,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertEquals(0, $company->documents()->count());

        // Asegurarse de que no queden archivos huérfanos en storage
        $filesInStorage = Storage::disk('private')->allFiles();
        $this->assertEmpty($filesInStorage);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'companies.update', 'documents.create']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        // Archivo que supera 10MB (11MB)
        $file = UploadedFile::fake()->create('archivo_gigante.pdf', 11 * 1024, 'application/pdf');

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'documentable_type' => 'company',
            'documentable_id' => $company->id,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertEquals(0, $company->documents()->count());
    }

    public function test_missing_file_on_disk_returns_404_without_revealing_server_paths(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'documents.view']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $doc = PrivateDocument::factory()->create([
            'documentable_type' => Company::class,
            'documentable_id' => $company->id,
            'file_path' => 'documents/company/non_existent_file.pdf',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('documents.download', $doc));
        $response->assertNotFound();
        // Asegurarse de que no imprime rutas de disco como C:\ ni /var/www
        $this->assertStringNotContainsString('C:\\', $response->getContent() ?: '');
        $this->assertStringNotContainsString('/storage/app', $response->getContent() ?: '');
    }

    public function test_authorized_user_can_delete_private_document(): void
    {
        $user = $this->makeUser(['Vendedor'], ['companies.view', 'companies.update', 'documents.create', 'documents.delete']);
        $company = Company::factory()->create(['owner_id' => $user->id]);

        $file = UploadedFile::fake()->create('doc_a_eliminar.pdf', 200, 'application/pdf');
        $this->actingAs($user)->post(route('documents.store'), [
            'documentable_type' => 'company',
            'documentable_id' => $company->id,
            'file' => $file,
        ]);

        $doc = $company->documents()->firstOrFail();
        $filePath = $doc->file_path;
        $this->assertTrue(Storage::disk('private')->exists($filePath));

        $delResponse = $this->actingAs($user)->delete(route('documents.destroy', $doc));
        $delResponse->assertRedirect();

        // El registro fue soft-deleted
        $this->assertSoftDeleted('private_documents', ['id' => $doc->id]);
        // Y el archivo físico fue removido
        $this->assertFalse(Storage::disk('private')->exists($filePath));

        // Registro de auditoría
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'document.deleted',
            'entity_id' => $doc->id,
        ]);
    }
}
