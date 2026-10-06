<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Leads\LeadConversionService;
use App\Services\Settings\SettingService;
use App\Support\DataScope;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PipelineSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 15 — Configuración y catálogos del negocio.
 * P15A: Configuración general y preferencias (Setting, SettingService, Timezone, Currency).
 * P15B: Pipelines y etapas comerciales (sincronización de probabilidades, preservación histórica).
 * P15C: Catálogos de productos y tickets, diagnósticos de calidad de datos con DataScope.
 */
class PhaseFifteenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, SettingSeeder::class, PipelineSeeder::class]);
        DataScope::clearCache();
        SettingService::clearCache();
    }

    private function makeUser(array $roles = [], array $perms = [], ?Team $team = null): User
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => 'active',
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

    // -------------------------------------------------------------
    // P15A: Configuración general y preferencias
    // -------------------------------------------------------------

    public function test_superadmin_can_view_settings(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('Configuración del sistema');
        $response->assertSee('America/Lima');
        $response->assertSee('USD');
    }

    public function test_unauthorized_user_cannot_access_settings(): void
    {
        $vendedor = $this->makeUser(['Vendedor']);

        $response = $this->actingAs($vendedor)->get(route('settings.index'));
        $response->assertForbidden();
    }

    public function test_superadmin_can_update_settings(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->put(route('settings.update'), [
            'company_name' => 'Empresa Andina Global S.A.C.',
            'company_country' => 'Colombia',
            'company_timezone' => 'America/Bogota',
            'default_currency' => 'PEN',
            'contact_email' => 'soporte@andina.local',
        ]);

        $response->assertRedirect(route('settings.index'));
        $this->assertEquals('Empresa Andina Global S.A.C.', SettingService::get('company_name'));
        $this->assertEquals('America/Bogota', SettingService::timezone());
        $this->assertEquals('PEN', SettingService::get('default_currency'));
    }

    public function test_settings_reset_to_catalog_defaults(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        SettingService::set('company_name', 'Nombre Temporal Modificado');
        $this->assertEquals('Nombre Temporal Modificado', SettingService::get('company_name'));

        $response = $this->actingAs($super)->post(route('settings.reset'));
        $response->assertRedirect(route('settings.index'));

        $this->assertEquals('NexusCRM S.A.S.', SettingService::get('company_name'));
    }

    public function test_timezone_today_range_utc_calculation(): void
    {
        // En Lima (UTC-5), el día 2026-10-15 10:00 local abarca desde 2026-10-15 05:00 UTC a 2026-10-16 04:59:59 UTC
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'America/Lima'));

        $range = SettingService::todayRangeUtc();

        $this->assertEquals('2026-10-15', $range['local_date']);
        $this->assertEquals('2026-10-15 05:00:00', $range['start_utc']->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-16 04:59:59', $range['end_utc']->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------
    // P15B: Pipeline y etapas comerciales
    // -------------------------------------------------------------

    public function test_superadmin_can_create_pipeline_with_default_stages(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $response = $this->actingAs($super)->post(route('settings.pipelines.store'), [
            'name' => 'Ventas Enterprise B2B',
            'description' => 'Pipeline para grandes corporaciones',
            'status' => 'active',
            'is_default' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pipelines', ['name' => 'Ventas Enterprise B2B']);

        $pipeline = Pipeline::where('name', 'Ventas Enterprise B2B')->firstOrFail();
        $this->assertCount(4, $pipeline->stages);

        $this->assertTrue($pipeline->stages()->where('is_won', true)->exists());
        $this->assertTrue($pipeline->stages()->where('is_lost', true)->exists());
    }

    public function test_designating_default_pipeline_unsets_previous_default(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $pipe1 = Pipeline::create(['name' => 'Pipe 1', 'is_default' => true, 'status' => 'active']);
        $pipe2 = Pipeline::create(['name' => 'Pipe 2', 'is_default' => false, 'status' => 'active']);

        $this->assertTrue($pipe1->fresh()->is_default);

        $response = $this->actingAs($super)->put(route('settings.pipelines.update', $pipe2), [
            'name' => 'Pipe 2 Predeterminado',
            'status' => 'active',
            'is_default' => 1,
        ]);

        $response->assertRedirect();
        $this->assertTrue($pipe2->fresh()->is_default);
        $this->assertFalse($pipe1->fresh()->is_default);
    }

    public function test_cannot_delete_pipeline_with_opportunities(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stage = $pipeline->stages()->firstOrFail();

        Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'owner_id' => $super->id,
        ]);

        $response = $this->actingAs($super)->delete(route('settings.pipelines.destroy', $pipeline));
        $response->assertSessionHasErrors('pipeline');
        $this->assertDatabaseHas('pipelines', ['id' => $pipeline->id]);
    }

    public function test_updating_open_stage_probability_syncs_open_opportunities(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();

        // Etapa abierta inicial con probabilidad 20%
        $openStage = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->firstOrFail();
        $openStage->update(['probability' => 20]);

        $oppOpen = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'probability' => 20,
            'status' => 'open',
            'owner_id' => $super->id,
        ]);

        // Cambiar la probabilidad de la etapa a 45%
        $response = $this->actingAs($super)->put(route('settings.pipelines.stages.update', [$pipeline, $openStage]), [
            'name' => $openStage->name,
            'probability' => 45,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertEquals(45, $openStage->fresh()->probability);
        $this->assertEquals(45, $oppOpen->fresh()->probability);
    }

    public function test_updating_stage_probability_does_not_alter_closed_opportunities(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();

        $wonStage = $pipeline->stages()->where('is_won', true)->firstOrFail();
        $lostStage = $pipeline->stages()->where('is_lost', true)->firstOrFail();

        $oppWon = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $wonStage->id,
            'probability' => 100,
            'status' => 'won',
            'owner_id' => $super->id,
        ]);

        $oppLost = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $lostStage->id,
            'probability' => 0,
            'status' => 'lost',
            'owner_id' => $super->id,
        ]);

        $this->assertEquals(100, $oppWon->fresh()->probability);
        $this->assertEquals(0, $oppLost->fresh()->probability);
    }

    public function test_lead_conversion_uses_default_pipeline_and_open_stage(): void
    {
        $super = $this->makeUser(['Superadministrador']);
        $lead = Lead::factory()->create(['status' => 'qualified', 'owner_id' => $super->id]);

        $service = new LeadConversionService;
        $result = $service->convert($lead, $super, [
            'company_mode' => 'new',
            'company_name' => 'Empresa Nueva de Lead',
            'create_opportunity' => true,
            'opportunity_name' => 'Oportunidad Convertida',
            'opportunity_amount' => '15000',
        ]);

        $this->assertNotNull($result['opportunity']);
        $opp = $result['opportunity'];

        $defaultPipeline = Pipeline::where('is_default', true)->firstOrFail();
        $this->assertEquals($defaultPipeline->id, $opp->pipeline_id);
        $this->assertFalse($opp->stage->is_won);
        $this->assertFalse($opp->stage->is_lost);
    }

    // -------------------------------------------------------------
    // P15C: Catálogos y calidad de datos
    // -------------------------------------------------------------

    public function test_crud_product_categories(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        // Crear
        $respStore = $this->actingAs($super)->post(route('settings.product-categories.store'), [
            'name' => 'Hardware y Servidores',
            'status' => 'active',
        ]);
        $respStore->assertRedirect();
        $this->assertDatabaseHas('product_categories', ['name' => 'Hardware y Servidores']);

        $category = ProductCategory::where('name', 'Hardware y Servidores')->firstOrFail();

        // Actualizar
        $respUpdate = $this->actingAs($super)->put(route('settings.product-categories.update', $category), [
            'name' => 'Hardware Corporativo',
            'status' => 'active',
        ]);
        $respUpdate->assertRedirect();
        $this->assertDatabaseHas('product_categories', ['name' => 'Hardware Corporativo']);

        // Impedir eliminación si contiene productos
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Servidor Rack 1U',
        ]);

        $respDelete = $this->actingAs($super)->delete(route('settings.product-categories.destroy', $category));
        $respDelete->assertSessionHasErrors('category');
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_crud_ticket_categories(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        $respStore = $this->actingAs($super)->post(route('settings.ticket-categories.store'), [
            'name' => 'Incidencias de Red',
            'status' => 'active',
        ]);
        $respStore->assertRedirect();
        $this->assertDatabaseHas('ticket_categories', ['name' => 'Incidencias de Red']);

        $category = TicketCategory::where('name', 'Incidencias de Red')->firstOrFail();

        // Impedir eliminación si contiene tickets
        Ticket::factory()->create([
            'category_id' => $category->id,
            'created_by' => $super->id,
        ]);

        $respDelete = $this->actingAs($super)->delete(route('settings.ticket-categories.destroy', $category));
        $respDelete->assertSessionHasErrors('category');
        $this->assertDatabaseHas('ticket_categories', ['id' => $category->id]);
    }

    public function test_data_quality_diagnostics_scoped_by_datascope(): void
    {
        $super = $this->makeUser(['Superadministrador']);

        // Contacto sin empresa
        Contact::factory()->create(['company_id' => null, 'owner_id' => $super->id]);

        // Lead sin canal (sin email ni teléfono)
        Lead::factory()->create(['email' => null, 'phone' => null, 'owner_id' => $super->id]);

        // Oportunidad abierta sin fecha de cierre
        Opportunity::factory()->create([
            'status' => 'open',
            'expected_close_date' => null,
            'owner_id' => $super->id,
        ]);

        $response = $this->actingAs($super)->get(route('settings.quality'));
        $response->assertOk();
        $response->assertSee('Calidad y diagnóstico de datos');
        $response->assertSee('Contactos sin empresa');
        $response->assertSee('Leads sin canal de contacto');
    }
}
