<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PrivateDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Documents\DocumentSecurityValidator;
use App\Services\Quotes\QuoteCalculator;
use App\Services\Settings\SettingService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * AuditorVerificationTest - Suite de Auditoría Integral (P-AUDIT).
 * Evalúa Calidad de Código, Seguridad contra ataques reales (IDOR, escalamiento,
 * sanitización de secretos, inyección) y Escalabilidad (N+1 queries, streaming de memoria).
 */
class AuditorVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    // =========================================================================
    // 1. AUDITORÍA DE SEGURIDAD (SECURITY AUDIT)
    // =========================================================================

    /**
     * [SEGURIDAD - IDOR 1]: Un vendedor de un equipo no puede visualizar ni editar
     * los registros comerciales pertenecientes a otro equipo.
     */
    public function test_security_idor_seller_cannot_access_or_mutate_other_team_company(): void
    {
        $vendCorp = User::where('email', 'marcos.diaz@demo.test')->firstOrFail();
        $vendPyme = User::where('email', 'valeria.ruiz@demo.test')->firstOrFail();

        $compCorp = Company::where('owner_id', $vendCorp->id)->firstOrFail();

        // Intento de GET directo (IDOR en visualización)
        $responseView = $this->actingAs($vendPyme)->get(route('companies.show', $compCorp));
        $responseView->assertStatus(403);

        // Intento de PUT directo (IDOR en mutación)
        $responseUpdate = $this->actingAs($vendPyme)->put(route('companies.update', $compCorp), [
            'trade_name' => 'Intento de Hackeo IDOR',
        ]);
        $responseUpdate->assertStatus(403);

        $this->assertNotEquals('Intento de Hackeo IDOR', $compCorp->fresh()->trade_name);
    }

    /**
     * [SEGURIDAD - IDOR 2]: Un usuario no autorizado no puede descargar adjuntos privados
     * manipulando el ID numérico en la URL de streaming.
     */
    public function test_security_idor_unauthorized_user_cannot_download_private_attachment(): void
    {
        Storage::fake('private');

        $vendCorp = User::where('email', 'marcos.diaz@demo.test')->firstOrFail();
        $vendPyme = User::where('email', 'valeria.ruiz@demo.test')->firstOrFail();

        $compCorp = Company::where('owner_id', $vendCorp->id)->firstOrFail();

        // Guardar documento privado asignado a la empresa de Marcos
        $path = 'documents/propuesta_confidencial.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 Fake PDF Content');

        $doc = PrivateDocument::create([
            'documentable_type' => Company::class,
            'documentable_id' => $compCorp->id,
            'user_id' => $vendCorp->id,
            'original_name' => 'propuesta_confidencial.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        // Valeria (otro equipo) intenta descargar el documento directamente
        $response = $this->actingAs($vendPyme)->get(route('documents.download', $doc));

        // Debe ser bloqueado por Policy + DataScope
        $response->assertStatus(403);
    }

    /**
     * [SEGURIDAD - Escalamiento de Privilegios]: Un Administrador regular no puede
     * autoasignarse rol de Superadministrador ni modificar a un Superadministrador.
     */
    public function test_security_privilege_escalation_is_strictly_blocked(): void
    {
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $superadmin = User::where('email', 'superadmin@demo.test')->firstOrFail();
        $roleSuper = Role::where('slug', 'superadministrador')->firstOrFail();

        // Intento 1: Administrador intentando editar al Superadministrador
        $responseEdit = $this->actingAs($admin)->put(route('admin.users.update', $superadmin), [
            'name' => 'Superadmin Hackeado',
            'email' => 'superadmin@demo.test',
        ]);
        $responseEdit->assertStatus(403);

        // Intento 2: Administrador intentando cambiar sus propios roles a Superadministrador
        $responseRole = $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => 'Admin Intento Superadmin',
            'email' => 'admin@demo.test',
            'roles' => [$roleSuper->id],
        ]);
        $responseRole->assertStatus(403);

        $this->assertFalse($admin->fresh()->isSuperAdmin());
    }

    /**
     * [SEGURIDAD - Cuentas Inactivas]: Si un usuario es marcado como inactivo,
     * cualquier petición subsiguiente invalida su sesión y redirige al login.
     */
    public function test_security_inactive_user_session_is_terminated_immediately(): void
    {
        $vendCorp = User::where('email', 'marcos.diaz@demo.test')->firstOrFail();

        // Desactivar administrativamente al usuario
        $vendCorp->update(['status' => 'inactive']);

        $response = $this->actingAs($vendCorp)->get(route('dashboard'));

        // Middleware EnsureActiveUser debe expulsarlo y redirigir
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * [SEGURIDAD - Sanitización de Auditoría]: El servicio de auditoría nunca persiste
     * contraseñas, tokens de sesión ni claves de aplicación (APP_KEY) en audit_logs.
     */
    public function test_security_audit_log_recursively_sanitizes_passwords_and_secrets(): void
    {
        $admin = User::where('email', 'superadmin@demo.test')->firstOrFail();
        $auditService = app(AuditService::class);

        $sensitivePayload = [
            'name' => 'Juan Actualizado',
            'password' => 'SecretoUltraSeguro123!',
            'password_confirmation' => 'SecretoUltraSeguro123!',
            'remember_token' => 'TokenSesionPrivadoX123',
            'app_key' => 'base64:ClaveMaestraSecreta',
            'nested' => [
                'api_secret' => 'SecretXYZ',
                'description' => 'Descripción segura',
            ],
        ];

        $auditService->log(
            actor: $admin,
            entity: $admin,
            action: 'user.security_test',
            oldValues: [],
            newValues: $sensitivePayload
        );

        $log = DB::table('audit_logs')
            ->where('action', 'user.security_test')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log);
        $newValuesJson = $log->new_values;

        $decoded = json_decode($newValuesJson, true);
        $this->assertIsArray($decoded);

        // Claves sensibles eliminadas
        $this->assertArrayNotHasKey('password', $decoded);
        $this->assertArrayNotHasKey('password_confirmation', $decoded);
        $this->assertArrayNotHasKey('remember_token', $decoded);
        $this->assertArrayNotHasKey('app_key', $decoded);
        $this->assertArrayNotHasKey('api_secret', $decoded['nested'] ?? []);

        // Campos legítimos conservados
        $this->assertSame('Juan Actualizado', $decoded['name']);
        $this->assertSame('Descripción segura', $decoded['nested']['description'] ?? null);
    }

    /**
     * [SEGURIDAD - Subida de Archivos]: DocumentSecurityValidator rechaza extensiones
     * o contenidos peligrosos (ej. .php).
     */
    public function test_security_validator_rejects_malicious_file_uploads(): void
    {
        $phpFile = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "hacked"; ?>');
        $validator = new DocumentSecurityValidator;

        $this->expectException(ValidationException::class);
        $validator->validate($phpFile);
    }

    // =========================================================================
    // 2. AUDITORÍA DE CALIDAD Y REGLAS DE NEGOCIO (QUALITY AUDIT)
    // =========================================================================

    /**
     * [CALIDAD - Precisión Decimal BCMath]: Evaluación de sumas e impuestos acumulativos
     * sin errores de redondeo IEEE-754.
     */
    public function test_quality_bcmath_large_commercial_calculation_accuracy(): void
    {
        $calculator = new QuoteCalculator;

        // Simular 50 líneas comerciales con valores fraccionarios
        $lines = [];
        for ($i = 0; $i < 50; $i++) {
            $lines[] = [
                'quantity' => '1.500',
                'unit_price' => '33.33',
                'discount_type' => 'none',
                'tax_rate' => '18.00',
            ];
        }

        $result = $calculator->calculate($lines);

        // Línea individual: 1.500 * 33.33 = 49.995 -> 49.99 (BCMath scale 2)
        // Impuesto línea: 49.99 * 0.18 = 8.9982 -> 8.99
        // 50 líneas: subtotal = 49.99 * 50 = 2499.50; tax = 8.99 * 50 = 449.50; total = 2949.00
        $this->assertSame('2499.50', $result['subtotal']);
        $this->assertSame('449.50', $result['tax_total']);
        $this->assertSame('2949.00', $result['total']);
    }

    /**
     * [CALIDAD - Configuración Protegida]: SettingService prohíbe terminantemente
     * claves que no existan en el catálogo o que intenten guardar credenciales.
     */
    public function test_quality_settings_rejects_prohibited_and_invalid_keys(): void
    {
        $this->expectException(ValidationException::class);
        SettingService::set('api_key_externa', 'valor_prohibido');
    }

    /**
     * [CALIDAD - Healthcheck]: Endpoint /up responde 200 OK en tiempo óptimo.
     */
    public function test_quality_healthcheck_endpoint_is_operational(): void
    {
        $start = microtime(true);
        $response = $this->get('/up');
        $durationMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(200, $durationMs, 'El healthcheck debe responder en menos de 200ms');
    }

    // =========================================================================
    // 3. AUDITORÍA DE ESCALABILIDAD Y RENDIMIENTO (SCALABILITY AUDIT)
    // =========================================================================

    /**
     * [ESCALABILIDAD - N+1 Queries]: La consulta de oportunidades y tablero Kanban
     * debe utilizar eager loading y mantener un número constante de queries.
     */
    public function test_scalability_kanban_and_pipeline_prevents_n_plus_one_queries(): void
    {
        $vendCorp = User::where('email', 'marcos.diaz@demo.test')->firstOrFail();
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stage = $pipeline->stages->firstOrFail();
        $comp = Company::where('owner_id', $vendCorp->id)->firstOrFail();
        $contact = Contact::where('company_id', $comp->id)->firstOrFail();

        // 1. Medir consultas con pocas oportunidades
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($vendCorp)->get(route('opportunities.kanban'));
        $queriesInitial = count(DB::getQueryLog());

        // 2. Insertar 20 oportunidades adicionales
        for ($i = 0; $i < 20; $i++) {
            Opportunity::create([
                'name' => "Oportunidad Escala {$i}",
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'company_id' => $comp->id,
                'contact_id' => $contact->id,
                'owner_id' => $vendCorp->id,
                'amount' => '1000.00',
                'currency' => 'USD',
                'probability' => 10,
                'expected_close_date' => now()->addDays(15)->toDateString(),
                'status' => 'open',
            ]);
        }

        // 3. Medir consultas tras aumentar la carga
        DB::flushQueryLog();
        $this->actingAs($vendCorp)->get(route('opportunities.kanban'));
        $queriesScaled = count(DB::getQueryLog());

        // El número de queries no debe multiplicarse por N (debe permanecer constante o delta <= 3)
        $this->assertLessThanOrEqual(
            $queriesInitial + 3,
            $queriesScaled,
            "Alerta de escalabilidad N+1: Las consultas aumentaron de {$queriesInitial} a {$queriesScaled}"
        );
    }

    /**
     * [ESCALABILIDAD - Streaming de Exportación]: Las exportaciones en CSV se transmiten
     * vía StreamedResponse para no saturar memoria RAM en exportaciones masivas.
     */
    public function test_scalability_exports_use_streamed_responses(): void
    {
        $admin = User::where('email', 'superadmin@demo.test')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('exports.module', ['module' => 'companies', 'format' => 'csv']));

        $response->assertStatus(200);
        $this->assertInstanceOf(
            StreamedResponse::class,
            $response->baseResponse,
            'La exportación masiva debe ser una StreamedResponse para garantizar escalabilidad de memoria'
        );
    }
}
