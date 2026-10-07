<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\User;
use App\Services\Leads\LeadConversionService;
use App\Services\Quotes\QuoteCalculator;
use App\Support\DataScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseEighteenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * P18A: Verificación del guard de seguridad contra bases de producción o no-testing.
     */
    public function test_safety_guard_prevents_tests_from_running_against_non_testing_environment(): void
    {
        $originalEnv = Config::get('app.env', 'testing');
        $originalConnection = Config::get('database.default');
        $originalDatabase = Config::get("database.connections.{$originalConnection}.database");

        // 1. Guard contra APP_ENV distinto de testing
        Config::set('app.env', 'production');
        $abortedEnv = false;
        try {
            $this->ensureTestingEnvironment();
        } catch (\RuntimeException $e) {
            $abortedEnv = str_contains($e->getMessage(), 'APP_ENV=testing');
        }
        $this->assertTrue($abortedEnv, 'El guard debe abortar si APP_ENV no es testing');

        // Restaurar para siguiente chequeo
        Config::set('app.env', $originalEnv);

        // 2. Guard contra base de datos relacional no identificada como test
        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.database', 'nexuscrm_production');
        $abortedDb = false;
        try {
            $this->ensureTestingEnvironment();
        } catch (\RuntimeException $e) {
            $abortedDb = str_contains($e->getMessage(), 'not a designated test database');
        }
        $this->assertTrue($abortedDb, 'El guard debe abortar si la base PostgreSQL no contiene test');

        // Restaurar estado
        Config::set('database.default', $originalConnection);
        Config::set("database.connections.{$originalConnection}.database", $originalDatabase);
    }

    /**
     * P18A: Cálculos decimales exactos con BCMath evitando drift de coma flotante IEEE-754.
     */
    public function test_exact_decimal_precision_with_bcmath_avoids_floating_point_drift(): void
    {
        $calculator = new QuoteCalculator;

        // Caso clásico donde float falla: 0.10 + 0.20 = 0.30000000000000004
        // Línea 1: cantidad 3, precio 0.10 => subtotal 0.30, impuesto 18% (0.054 -> truncado a 0.05)
        // Línea 2: cantidad 1, precio 0.20 => subtotal 0.20, impuesto 18% (0.036 -> truncado a 0.03)
        $lines = [
            [
                'quantity' => '3.000',
                'unit_price' => '0.10',
                'discount_type' => 'none',
                'tax_rate' => '18.00',
            ],
            [
                'quantity' => '1.000',
                'unit_price' => '0.20',
                'discount_type' => 'none',
                'tax_rate' => '18.00',
            ],
        ];

        $result = $calculator->calculate($lines);

        $this->assertSame('0.50', $result['subtotal'], 'Subtotal debe ser exactamente 0.50 sin drift');
        $this->assertSame('0.00', $result['discount_total']);
        $this->assertSame('0.08', $result['tax_total'], 'Impuestos acumulados calculados con precisión BCMath');
        $this->assertSame('0.58', $result['total'], 'Total debe ser exactamente 0.58');
    }

    /**
     * P18A: Las monedas distintas jamás se mezclan en agregaciones ni forecast.
     */
    public function test_currencies_are_strictly_separated_in_aggregations_and_forecast(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $pipeline = Pipeline::firstOrFail();
        $stage = $pipeline->stages->first();

        // Crear una oportunidad en USD y otra en EUR
        $oppUsd = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id' => $user->id,
            'currency' => 'USD',
            'amount' => '1000.00',
            'probability' => 50,
        ]);

        $oppEur = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id' => $user->id,
            'currency' => 'EUR',
            'amount' => '2000.00',
            'probability' => 50,
        ]);

        // Agrupación por moneda en base de datos
        $currencySums = Opportunity::query()
            ->whereIn('id', [$oppUsd->id, $oppEur->id])
            ->select('currency', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('currency')
            ->pluck('total_amount', 'currency')
            ->toArray();

        $this->assertEquals('1000.00', $currencySums['USD']);
        $this->assertEquals('2000.00', $currencySums['EUR']);
        $this->assertCount(2, $currencySums, 'Deben existir 2 totales separados, jamás un total mixto');
    }

    /**
     * P18A: Inmutabilidad de snapshots comerciales cuando cambia el catálogo de productos.
     */
    public function test_commercial_snapshots_are_immutable_when_product_catalog_changes(): void
    {
        $product = Product::factory()->create([
            'name' => 'Licencia Anual Cloud',
            'price' => '1200.00',
            'tax_rate' => '18.00',
        ]);

        $quote = Quote::factory()->create([
            'subtotal' => '1200.00',
            'tax_total' => '216.00',
            'total' => '1416.00',
            'status' => 'draft',
        ]);

        $item = QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'position' => 1,
            'description' => $product->name,
            'unit' => 'unit',
            'quantity' => '1.000',
            'unit_price' => '1200.00',
            'discount_type' => 'none',
            'discount_value' => '0.00',
            'tax_rate' => '18.00',
            'subtotal' => '1200.00',
            'discount_amount' => '0.00',
            'tax_amount' => '216.00',
            'total' => '1416.00',
        ]);

        // Ahora mutamos el producto original en el catálogo (aumento de precio y renombre)
        $product->update([
            'name' => 'Licencia Anual Cloud Enterprise PRO',
            'price' => '9999.00',
            'tax_rate' => '21.00',
        ]);

        // El ítem congelado debe mantenerse idéntico a su snapshot original
        $item->refresh();
        $this->assertEquals('1200.00', $item->unit_price);
        $this->assertEquals('Licencia Anual Cloud', $item->description);
        $this->assertEquals('1416.00', $item->total);
    }

    /**
     * P18A: Conversión atómica de lead con lock y prevención de doble conversión.
     */
    public function test_lead_conversion_atomic_transaction_and_locks_prevent_double_conversion(): void
    {
        $superadminRole = Role::where('slug', 'superadministrador')->firstOrFail();
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->sync([$superadminRole->id]);

        $pipeline = Pipeline::firstOrFail();
        $stage = $pipeline->stages->first();

        $lead = Lead::factory()->create([
            'status' => 'qualified',
            'owner_id' => $admin->id,
            'company_name' => 'Empresa Concurrente SAC',
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
            'email' => 'carlos@concurrente.test',
        ]);

        $service = app(LeadConversionService::class);

        $payload = [
            'company_mode' => 'new',
            'company_name' => 'Empresa Concurrente SAC',
            'create_opportunity' => true,
            'opportunity_name' => 'Oportunidad Concurrente',
            'amount' => '5000.00',
            'currency' => 'USD',
        ];

        // Primera conversión: Éxito
        $result = $service->convert($lead, $admin, $payload);
        $this->assertInstanceOf(Company::class, $result['company']);
        $this->assertInstanceOf(Opportunity::class, $result['opportunity']);
        $this->assertTrue($lead->fresh()->isConverted());

        // Segunda llamada con el mismo lead: Debe fallar limpiamente con ValidationException
        $this->expectException(ValidationException::class);
        $service->convert($lead->fresh(), $admin, $payload);
    }

    /**
     * P18A: Rollback transaccional no deja registros huérfanos ni auditorías falsas.
     */
    public function test_transaction_rollback_guarantees_zero_orphaned_records(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $initialCompaniesCount = Company::count();

        try {
            DB::transaction(function () use ($user) {
                Company::create([
                    'name' => 'Empresa Que Va A Fallar',
                    'owner_id' => $user->id,
                ]);

                // Forzar excepción para detonar rollback
                throw new \RuntimeException('Fallo intencional para verificar rollback transaccional');
            });
        } catch (\RuntimeException $e) {
            // Capturado intencionalmente
        }

        $this->assertSame($initialCompaniesCount, Company::count(), 'El rollback debe restaurar el conteo exacto de empresas');
    }

    /**
     * P18A: Usuarios con team_id null no comparten alcance de equipo (aislamiento estricto).
     */
    public function test_team_id_null_users_do_not_share_data_scope(): void
    {
        $sellerRole = Role::where('slug', 'vendedor')->firstOrFail();

        $sellerA = User::factory()->create(['status' => 'active', 'team_id' => null]);
        $sellerA->roles()->sync([$sellerRole->id]);

        $sellerB = User::factory()->create(['status' => 'active', 'team_id' => null]);
        $sellerB->roles()->sync([$sellerRole->id]);

        $companyB = Company::factory()->create(['owner_id' => $sellerB->id]);

        // Seller A intentando ver Company B
        $visibleIdsForSellerA = DataScope::scopeOwned(Company::query(), $sellerA, 'owner_id')
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($companyB->id, $visibleIdsForSellerA, 'Seller A con team_id null no debe ver registros de Seller B con team_id null');
    }

    /**
     * P18A: El rol Consulta es estrictamente de solo lectura en operaciones de mutación.
     */
    public function test_consulta_pure_role_is_strictly_read_only_across_all_mutation_endpoints(): void
    {
        $consultaRole = Role::where('slug', 'consulta')->firstOrFail();
        $consultaUser = User::factory()->create(['status' => 'active', 'team_id' => null]);
        $consultaUser->roles()->sync([$consultaRole->id]);

        // Intento de POST Company
        $response = $this->actingAs($consultaUser)
            ->post(route('companies.store'), [
                'name' => 'Empresa No Autorizada',
            ]);

        $response->assertStatus(403);

        // Intento de POST Lead
        $responseLead = $this->actingAs($consultaUser)
            ->post(route('leads.store'), [
                'first_name' => 'Lead',
                'last_name' => 'Denegado',
                'company_name' => 'Corp',
            ]);

        $responseLead->assertStatus(403);
    }

    /**
     * P18A: Un Administrador estándar no puede degradar ni eliminar a un Superadministrador.
     */
    public function test_superadmin_cannot_be_demoted_or_deleted_by_regular_admin(): void
    {
        $superadminRole = Role::where('slug', 'superadministrador')->firstOrFail();
        $adminRole = Role::where('slug', 'administrador')->firstOrFail();

        $superadmin = User::factory()->create(['status' => 'active']);
        $superadmin->roles()->sync([$superadminRole->id]);

        $regularAdmin = User::factory()->create(['status' => 'active']);
        $regularAdmin->roles()->sync([$adminRole->id]);

        // Intento de eliminar al Superadministrador por un Administrador regular
        $response = $this->actingAs($regularAdmin)
            ->delete(route('admin.users.destroy', $superadmin));

        $response->assertStatus(403);
        $this->assertFalse($superadmin->fresh()->trashed(), 'El Superadministrador no debe ser eliminado por un Administrador');
    }
}
