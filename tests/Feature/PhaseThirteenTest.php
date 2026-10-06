<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DataImport;
use App\Models\DataImportError;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Imports\ImportService;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 13 — Importaciones y Exportaciones.
 * P13A: Importación CSV con validación, DataScope, preview y reporte de errores.
 */
class PhaseThirteenTest extends TestCase
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
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

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

    public function test_guest_is_redirected_to_login_on_imports(): void
    {
        $response = $this->get(route('imports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_access_imports(): void
    {
        $user = $this->makeUser(['companies.view']);
        $response = $this->actingAs($user)->get(route('imports.index'));
        $response->assertForbidden();
    }

    public function test_user_with_imports_view_can_access_index(): void
    {
        $user = $this->makeUser(['imports.view']);
        $response = $this->actingAs($user)->get(route('imports.index'));
        $response->assertOk();
        $response->assertSee('Historial de importaciones');
    }

    public function test_user_without_imports_create_cannot_access_create(): void
    {
        $user = $this->makeUser(['imports.view']);
        $response = $this->actingAs($user)->get(route('imports.create'));
        $response->assertForbidden();
    }

    public function test_user_can_download_official_csv_template(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create']);
        $response = $this->actingAs($user)->get(route('imports.template', ['module' => 'companies']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->getContent();

        // Verificar BOM UTF-8 y encabezados
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('trade_name', $content);
        $this->assertStringContainsString('legal_name', $content);
        $this->assertStringContainsString('tax_id', $content);
    }

    public function test_template_download_aborts_for_invalid_module(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create']);
        $response = $this->actingAs($user)->get(route('imports.template', ['module' => 'invalid_module']));
        $response->assertNotFound();
    }

    public function test_upload_csv_displays_mapping_screen(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create']);

        $csvContent = "Nombre Comercial,Razon Social,NIT/RUC,Email Corporativo\nAcme Corp,Acme S.A.S,900123456-1,contacto@acme.com\nBeta SAS,Beta Corp,900654321-2,info@beta.com";
        $file = UploadedFile::fake()->createWithContent('empresas.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('imports.upload'), [
            'module' => 'companies',
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertViewIs('imports.mapping');
        $response->assertViewHas('headers', ['Nombre Comercial', 'Razon Social', 'NIT/RUC', 'Email Corporativo']);
        $response->assertViewHas('totalRows', 2);
    }

    public function test_preview_validates_rows_and_generates_cache_token(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create']);

        $csvContent = "trade_name,tax_id,email\nAcme SAS,900111222-1,acme@test.com\nBeta Limitada,900333444-2,beta@test.com";
        $file = UploadedFile::fake()->createWithContent('empresas.csv', $csvContent);

        $importService = app(ImportService::class);
        $stored = $importService->storeTempFile($file, 'companies');

        $response = $this->actingAs($user)->post(route('imports.preview'), [
            'module' => 'companies',
            'temp_path' => $stored['temp_path'],
            'original_filename' => 'empresas.csv',
            'mapping' => [
                'trade_name' => 'trade_name',
                'tax_id' => 'tax_id',
                'email' => 'email',
            ],
        ]);

        $response->assertOk();
        $response->assertViewIs('imports.preview');
        $response->assertViewHas('totalRows', 2);
        $response->assertViewHas('validCount', 2);
        $response->assertViewHas('errorCount', 0);
        $this->assertNotEmpty($response->viewData('token'));
    }

    public function test_confirm_import_creates_records_and_is_atomic(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create'], roles: ['Administrador']);

        $csvContent = "trade_name,tax_id,email,phone\nEmpresa Uno,800111001-1,uno@corp.com,018000123\nEmpresa Dos,800111002-2,dos@corp.com,018000456";
        $file = UploadedFile::fake()->createWithContent('empresas.csv', $csvContent);

        $importService = app(ImportService::class);
        $stored = $importService->storeTempFile($file, 'companies');

        $preview = $importService->generatePreview(
            'companies',
            $stored['temp_path'],
            'empresas.csv',
            [
                'trade_name' => 'trade_name',
                'tax_id' => 'tax_id',
                'email' => 'email',
                'phone' => 'phone',
            ],
            $user
        );

        $token = $preview['token'];
        $this->assertTrue(Cache::has("import_confirm_{$token}"));

        // Confirmar importación
        $response = $this->actingAs($user)->post(route('imports.confirm'), [
            'token' => $token,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('companies', [
            'trade_name' => 'Empresa Uno',
            'tax_id' => '800111001-1',
            'phone' => '018000123', // Conserva ceros a la izquierda
        ]);
        $this->assertDatabaseHas('companies', [
            'trade_name' => 'Empresa Dos',
            'tax_id' => '800111002-2',
        ]);

        // Verificar registro de DataImport
        $this->assertDatabaseHas('data_imports', [
            'module' => 'companies',
            'total_rows' => 2,
            'successful_rows' => 2,
            'failed_rows' => 0,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        // Token fue consumido atómicamente; un reintento no debe procesar de nuevo
        $this->assertFalse(Cache::has("import_confirm_{$token}"));
        $retryResponse = $this->actingAs($user)->post(route('imports.confirm'), [
            'token' => $token,
        ]);
        $retryResponse->assertRedirect(route('imports.index'));
        $retryResponse->assertSessionHasErrors(['token']);
    }

    public function test_import_with_partial_failures_records_errors(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create'], roles: ['Administrador']);

        // Crear una empresa existente para provocar conflicto de NIT/RUC único
        Company::factory()->create([
            'tax_id' => '999999999-9',
            'owner_id' => $user->id,
        ]);

        // CSV con 1 válida y 1 duplicada
        $csvContent = "trade_name,tax_id\nEmpresa Nueva,888888888-8\nEmpresa Duplicada,999999999-9";
        $file = UploadedFile::fake()->createWithContent('empresas.csv', $csvContent);

        $importService = app(ImportService::class);
        $stored = $importService->storeTempFile($file, 'companies');

        $preview = $importService->generatePreview(
            'companies',
            $stored['temp_path'],
            'empresas.csv',
            [
                'trade_name' => 'trade_name',
                'tax_id' => 'tax_id',
            ],
            $user
        );

        $response = $this->actingAs($user)->post(route('imports.confirm'), [
            'token' => $preview['token'],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('companies', ['trade_name' => 'Empresa Nueva']);
        $this->assertDatabaseMissing('companies', ['trade_name' => 'Empresa Duplicada']);

        $import = DataImport::latest('id')->first();
        $this->assertEquals(2, $import->total_rows);
        $this->assertEquals(1, $import->successful_rows);
        $this->assertEquals(1, $import->failed_rows);

        $this->assertDatabaseHas('data_import_errors', [
            'data_import_id' => $import->id,
            'row_number' => 3, // fila 3 del CSV (encabezado es 1, fila 1 es 2, fila 2 es 3)
        ]);

        // Descarga de errores
        $errorResponse = $this->actingAs($user)->get(route('imports.errors', $import));
        $errorResponse->assertOk();
        $this->assertStringContainsString('999999999-9', $errorResponse->getContent());
    }

    public function test_errors_csv_neutralizes_formula_injection(): void
    {
        $user = $this->makeUser(['imports.view', 'imports.create', 'companies.create'], roles: ['Administrador']);

        $import = DataImport::create([
            'module' => 'companies',
            'original_filename' => 'hack.csv',
            'total_rows' => 1,
            'successful_rows' => 0,
            'failed_rows' => 1,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        DataImportError::create([
            'data_import_id' => $import->id,
            'row_number' => 2,
            'field' => 'name',
            'message' => '=CMD|/C calc.exe',
        ]);

        $response = $this->actingAs($user)->get(route('imports.errors', $import));
        $response->assertOk();
        $content = $response->getContent();

        // Debe haber sido neutralizado anteponiendo una comilla simple
        $this->assertStringContainsString("'=CMD|/C calc.exe", $content);
    }

    public function test_contacts_import_respects_datascope_for_companies(): void
    {
        $teamA = $this->makeTeam('ventas-norte');
        $teamB = $this->makeTeam('ventas-sur');

        $userA = $this->makeUser(['imports.view', 'imports.create', 'contacts.create'], team: $teamA, roles: ['Vendedor']);
        $userB = $this->makeUser(['companies.create'], team: $teamB, roles: ['Vendedor']);

        // Empresa privada de User B
        $companyB = Company::factory()->create([
            'owner_id' => $userB->id,
        ]);

        // Usuario A intenta importar contacto vinculado a la empresa de Team B
        $csvContent = "first_name,last_name,email,company_id\nJuan,Perez,juan@perez.com,{$companyB->id}";
        $file = UploadedFile::fake()->createWithContent('contactos.csv', $csvContent);

        $importService = app(ImportService::class);
        $stored = $importService->storeTempFile($file, 'contacts');

        $preview = $importService->generatePreview(
            'contacts',
            $stored['temp_path'],
            'contactos.csv',
            [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
                'company_id' => 'company_id',
            ],
            $userA
        );

        $this->actingAs($userA)->post(route('imports.confirm'), [
            'token' => $preview['token'],
        ]);

        // El contacto no debe crearse porque la empresa está fuera del DataScope de User A
        $this->assertDatabaseMissing('contacts', ['email' => 'juan@perez.com']);

        $import = DataImport::latest('id')->first();
        $this->assertEquals(1, $import->failed_rows);
        $this->assertDatabaseHas('data_import_errors', [
            'data_import_id' => $import->id,
            'row_number' => 2,
        ]);
    }

    public function test_guest_cannot_access_export(): void
    {
        $response = $this->get(route('exports.module', ['module' => 'companies']));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_exports_view_cannot_export(): void
    {
        $user = $this->makeUser(['companies.view']);
        $response = $this->actingAs($user)->get(route('exports.module', ['module' => 'companies']));
        $response->assertForbidden();
    }

    public function test_user_without_module_view_permission_cannot_export(): void
    {
        $user = $this->makeUser(['exports.view']); // falta companies.view
        $response = $this->actingAs($user)->get(route('exports.module', ['module' => 'companies']));
        $response->assertForbidden();
    }

    public function test_user_can_export_companies_csv_streamed(): void
    {
        $user = $this->makeUser(['exports.view', 'companies.view'], roles: ['Administrador']);

        Company::factory()->create([
            'trade_name' => 'Tech Solutions S.A.',
            'tax_id' => '900999888-1',
            'owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('exports.module', ['module' => 'companies']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Nombre Comercial', $content);
        $this->assertStringContainsString('Tech Solutions S.A.', $content);
        $this->assertStringContainsString('900999888-1', $content);
    }

    public function test_export_enforces_datascope(): void
    {
        $userA = $this->makeUser(['exports.view', 'companies.view'], roles: ['Vendedor']);
        $userB = $this->makeUser(['exports.view', 'companies.view'], roles: ['Vendedor']);

        // Empresa de User A
        Company::factory()->create([
            'trade_name' => 'Empresa de Vendedor A',
            'owner_id' => $userA->id,
        ]);

        // Empresa de User B
        Company::factory()->create([
            'trade_name' => 'Empresa de Vendedor B',
            'owner_id' => $userB->id,
        ]);

        // Vendedor A exporta: solo debe ver su propia empresa
        $responseA = $this->actingAs($userA)->get(route('exports.module', ['module' => 'companies']));
        $contentA = $responseA->streamedContent();

        $this->assertStringContainsString('Empresa de Vendedor A', $contentA);
        $this->assertStringNotContainsString('Empresa de Vendedor B', $contentA);
    }

    public function test_export_respects_query_filters(): void
    {
        $user = $this->makeUser(['exports.view', 'companies.view'], roles: ['Administrador']);

        Company::factory()->create([
            'trade_name' => 'Alfa Corp',
            'status' => 'active',
            'owner_id' => $user->id,
        ]);

        Company::factory()->create([
            'trade_name' => 'Beta Inactiva',
            'status' => 'inactive',
            'owner_id' => $user->id,
        ]);

        // Filtrar por status=active
        $response = $this->actingAs($user)->get(route('exports.module', [
            'module' => 'companies',
            'status' => 'active',
        ]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Alfa Corp', $content);
        $this->assertStringNotContainsString('Beta Inactiva', $content);
    }

    public function test_export_neutralizes_formula_injection(): void
    {
        $user = $this->makeUser(['exports.view', 'companies.view'], roles: ['Administrador']);

        Company::factory()->create([
            'trade_name' => '=HYPERLINK("http://evil.com","Click")',
            'owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('exports.module', ['module' => 'companies']));
        $content = $response->streamedContent();

        // Debe haber sido neutralizado con comilla simple
        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_export_aborts_for_unsupported_module(): void
    {
        $user = $this->makeUser(['exports.view']);
        $response = $this->actingAs($user)->get(route('exports.module', ['module' => 'unknown_mod']));
        $response->assertNotFound();
    }

    public function test_ticket_print_view(): void
    {
        $user = $this->makeUser(['tickets.view'], roles: ['Administrador']);

        $ticket = Ticket::create([
            'number' => 'TKT-2026-000001',
            'subject' => 'Problema con acceso al portal',
            'description' => 'El cliente no puede ingresar al sistema.',
            'status' => 'open',
            'priority' => 'high',
            'channel' => 'web',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.print', $ticket));
        $response->assertOk();
        $response->assertSee('TKT-2026-000001');
        $response->assertSee('Problema con acceso al portal');
        $response->assertSee('El cliente no puede ingresar al sistema.');
    }
}
