<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Team;
use App\Models\Ticket;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PhaseNineteenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * P19A: DemoSeeder genera un dataset coherente y estructurado para 2 equipos.
     */
    public function test_demo_seeder_creates_coherent_multiteam_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        // 1. Verificación de dos equipos comerciales
        $this->assertDatabaseHas('teams', ['slug' => 'ventas-corporativas']);
        $this->assertDatabaseHas('teams', ['slug' => 'ventas-pyme']);
        $this->assertEquals(2, Team::count());

        // 2. Usuarios con roles correctos
        $this->assertDatabaseHas('users', ['email' => 'superadmin@demo.test']);
        $this->assertDatabaseHas('users', ['email' => 'marcos.diaz@demo.test']);
        $this->assertDatabaseHas('users', ['email' => 'valeria.ruiz@demo.test']);
        $this->assertDatabaseHas('users', ['email' => 'david.soporte@demo.test']);
        $this->assertDatabaseHas('users', ['email' => 'auditor.consulta@demo.test']);

        // 3. Cartera y ciclo comercial completo
        $this->assertGreaterThanOrEqual(3, Company::count());
        $this->assertGreaterThanOrEqual(4, Lead::count());
        $this->assertDatabaseHas('leads', ['status' => 'new']);
        $this->assertDatabaseHas('leads', ['status' => 'qualified']);

        // 4. Oportunidades y documentos comerciales encadenados
        $this->assertGreaterThanOrEqual(4, Opportunity::count());
        $quote = Quote::where('number', 'COT-DEMO-001')->first();
        $this->assertNotNull($quote);
        $this->assertEquals('accepted', $quote->status);
        $this->assertGreaterThan(0, $quote->items()->count());

        $sale = Sale::where('number', 'VNT-DEMO-001')->first();
        $this->assertNotNull($sale);
        $this->assertEquals('completed', $sale->status);

        $invoice = Invoice::where('number', 'FAC-DEMO-001')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('paid', $invoice->status);

        // 5. Tickets de soporte
        $ticket = Ticket::where('number', 'TCK-DEMO-001')->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('resolved', $ticket->status);
        $this->assertGreaterThan(0, $ticket->messages()->count());
    }

    /**
     * P19A: El comando crm:demo-reset aborta inmediatamente en entorno productivo.
     */
    public function test_demo_reset_command_aborts_in_production(): void
    {
        Config::set('app.env', 'production');

        $exitCode = Artisan::call('crm:demo-reset');

        $this->assertEquals(1, $exitCode, 'El comando debe fallar con código 1 en producción');
        $output = Artisan::output();
        $this->assertStringContainsString('no puede ejecutarse en entorno de producción', $output);

        Config::set('app.env', 'testing');
    }

    /**
     * P19A: El comando crm:demo-reset se ejecuta exitosamente en entorno de pruebas.
     */
    public function test_demo_reset_command_succeeds_in_testing(): void
    {
        $exitCode = Artisan::call('crm:demo-reset');

        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('users', ['email' => 'superadmin@demo.test']);
    }

    /**
     * P19B: Endpoint de healthcheck /up responde HTTP 200 y no expone credenciales.
     */
    public function test_healthcheck_endpoint_returns_ok_without_leaking_secrets(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
        $content = $response->getContent();

        // No debe filtrar datos sensibles
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('nexuscrm_test_secret', $content);
    }

    /**
     * P19C: Comando crm:backup genera un paquete verificable con manifiesto.
     */
    public function test_backup_system_command_generates_and_verifies_archive(): void
    {
        $this->seed(DemoSeeder::class);

        $exitCode = Artisan::call('crm:backup', ['--verify' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Verificación de integridad exitosa', $output);

        // Limpiar archivos generados durante la prueba
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            File::cleanDirectory($backupDir);
        }
    }
}
