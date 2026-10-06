<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de Demostración y Evaluación Laboral (P19A).
 * Genera un conjunto sintético y coherente de datos para dos equipos comerciales,
 * cartera de clientes, ciclo comercial completo (Lead -> Opp -> Cotización -> Venta -> Factura interna),
 * tickets de soporte con conversación, tareas y campañas simuladas.
 *
 * Idempotente y seguro: solo utiliza dominios reservados de prueba (.test).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Asegurar catálogos base del sistema
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SettingSeeder::class,
            PipelineSeeder::class,
            ProductSeeder::class,
            TicketCategorySeeder::class,
        ]);

        // 2. Equipos comerciales aislados
        $teamCorp = Team::firstOrCreate(
            ['slug' => 'ventas-corporativas'],
            ['name' => 'Equipo Ventas Corporativas', 'description' => 'Atención a cuentas grandes y enterprise.', 'status' => 'active']
        );

        $teamPyme = Team::firstOrCreate(
            ['slug' => 'ventas-pyme'],
            ['name' => 'Equipo Ventas Pyme', 'description' => 'Atención ágil a pequeñas y medianas empresas.', 'status' => 'active']
        );

        // 3. Roles de acceso
        $roleSuper = Role::where('slug', 'superadministrador')->firstOrFail();
        $roleAdmin = Role::where('slug', 'administrador')->firstOrFail();
        $roleSupervisor = Role::where('slug', 'supervisor')->firstOrFail();
        $roleVendedor = Role::where('slug', 'vendedor')->firstOrFail();
        $roleSoporte = Role::where('slug', 'soporte')->firstOrFail();
        $roleConsulta = Role::where('slug', 'consulta')->firstOrFail();

        $passwordHash = Hash::make('password');

        // 4. Usuarios con distintos roles y equipos
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@demo.test'],
            ['name' => 'Superadministrador Nexus', 'password' => $passwordHash, 'team_id' => null, 'status' => 'active']
        );
        $superadmin->roles()->sync([$roleSuper->id]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Administrador Operativo', 'password' => $passwordHash, 'team_id' => null, 'status' => 'active']
        );
        $admin->roles()->sync([$roleAdmin->id]);

        // Equipo 1: Corporativo
        $supCorp = User::firstOrCreate(
            ['email' => 'supervisor.corp@demo.test'],
            ['name' => 'Elena Torres (Sup. Corp)', 'password' => $passwordHash, 'team_id' => $teamCorp->id, 'status' => 'active']
        );
        $supCorp->roles()->sync([$roleSupervisor->id]);

        $vendCorp = User::firstOrCreate(
            ['email' => 'marcos.diaz@demo.test'],
            ['name' => 'Marcos Díaz (Vendedor Corp)', 'password' => $passwordHash, 'team_id' => $teamCorp->id, 'status' => 'active']
        );
        $vendCorp->roles()->sync([$roleVendedor->id]);

        // Equipo 2: Pyme
        $supPyme = User::firstOrCreate(
            ['email' => 'supervisor.pyme@demo.test'],
            ['name' => 'Roberto Gómez (Sup. Pyme)', 'password' => $passwordHash, 'team_id' => $teamPyme->id, 'status' => 'active']
        );
        $supPyme->roles()->sync([$roleSupervisor->id]);

        $vendPyme = User::firstOrCreate(
            ['email' => 'valeria.ruiz@demo.test'],
            ['name' => 'Valeria Ruiz (Vendedora Pyme)', 'password' => $passwordHash, 'team_id' => $teamPyme->id, 'status' => 'active']
        );
        $vendPyme->roles()->sync([$roleVendedor->id]);

        // Soporte y Consulta
        $soporte = User::firstOrCreate(
            ['email' => 'david.soporte@demo.test'],
            ['name' => 'David Castro (Soporte)', 'password' => $passwordHash, 'team_id' => null, 'status' => 'active']
        );
        $soporte->roles()->sync([$roleSoporte->id]);

        $consulta = User::firstOrCreate(
            ['email' => 'auditor.consulta@demo.test'],
            ['name' => 'Auditor Externo (Solo Lectura)', 'password' => $passwordHash, 'team_id' => null, 'status' => 'active']
        );
        $consulta->roles()->sync([$roleConsulta->id]);

        // 5. Empresas y Contactos (Equipo 1: Marcos Díaz)
        $compCorp1 = Company::firstOrCreate(
            ['trade_name' => 'Soluciones Tecnológicas Andinas SAC'],
            [
                'legal_name' => 'Soluciones Tecnológicas Andinas Sociedad Anónima Cerrada',
                'tax_id' => '20501234561',
                'email' => 'contacto@andinas.test',
                'phone' => '+51 1 555 1001',
                'website' => 'https://andinas.test',
                'status' => 'active',
                'owner_id' => $vendCorp->id,
            ]
        );
        $contactCorp1 = Contact::firstOrCreate(
            ['email' => 'jperez@andinas.test'],
            [
                'company_id' => $compCorp1->id,
                'first_name' => 'Juan',
                'last_name' => 'Pérez Ramos',
                'job_title' => 'Director de TI',
                'phone' => '+51 987 654 321',
                'status' => 'active',
                'owner_id' => $vendCorp->id,
            ]
        );

        $compCorp2 = Company::firstOrCreate(
            ['trade_name' => 'Distribuidora Internacional Pacífico SA'],
            [
                'legal_name' => 'Distribuidora Internacional Pacífico Sociedad Anónima',
                'tax_id' => '20609876542',
                'email' => 'ventas@pacifico.test',
                'phone' => '+51 1 555 2002',
                'website' => 'https://pacifico.test',
                'status' => 'active',
                'owner_id' => $vendCorp->id,
            ]
        );
        $contactCorp2 = Contact::firstOrCreate(
            ['email' => 'amorales@pacifico.test'],
            [
                'company_id' => $compCorp2->id,
                'first_name' => 'Ana',
                'last_name' => 'Morales Vega',
                'job_title' => 'Gerente de Compras',
                'phone' => '+51 987 112 233',
                'status' => 'active',
                'owner_id' => $vendCorp->id,
            ]
        );

        // Empresas y Contactos (Equipo 2: Valeria Ruiz)
        $compPyme1 = Company::firstOrCreate(
            ['trade_name' => 'Consultoría e Innovación Lima SRL'],
            [
                'legal_name' => 'Consultoría e Innovación Lima Sociedad Comercial de Resp. Ltda.',
                'tax_id' => '20405556663',
                'email' => 'hola@lima-innova.test',
                'phone' => '+51 1 555 3003',
                'status' => 'active',
                'owner_id' => $vendPyme->id,
            ]
        );
        $contactPyme1 = Contact::firstOrCreate(
            ['email' => 'cmendoza@lima-innova.test'],
            [
                'company_id' => $compPyme1->id,
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza Aliaga',
                'job_title' => 'Gerente General',
                'phone' => '+51 999 445 566',
                'status' => 'active',
                'owner_id' => $vendPyme->id,
            ]
        );

        // 6. Leads en diferentes estados
        Lead::firstOrCreate(
            ['email' => 'pvargas@mineranorte.test'],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Vargas Quispe',
                'company_name' => 'Iniciativa Minera del Norte',
                'source' => 'website',
                'status' => 'new',
                'owner_id' => $vendCorp->id,
            ]
        );

        Lead::firstOrCreate(
            ['email' => 'srios@logistica.test'],
            [
                'first_name' => 'Sofía',
                'last_name' => 'Ríos Mendoza',
                'company_name' => 'Servicios Logísticos Globales',
                'source' => 'referral',
                'status' => 'contacted',
                'owner_id' => $vendPyme->id,
            ]
        );

        Lead::firstOrCreate(
            ['email' => 'mbeltran@hotelvalle.test'],
            [
                'first_name' => 'Martín',
                'last_name' => 'Beltrán Flores',
                'company_name' => 'Cadena Hotelera del Valle SAC',
                'source' => 'campaign',
                'status' => 'qualified',
                'owner_id' => $vendCorp->id,
            ]
        );

        Lead::firstOrCreate(
            ['email' => 'contacto@tallerexpress.test'],
            [
                'first_name' => 'Jorge',
                'last_name' => 'Campos Soto',
                'company_name' => 'Taller Mecánico Express',
                'source' => 'phone',
                'status' => 'unqualified',
                'owner_id' => $vendPyme->id,
            ]
        );

        // 7. Oportunidades en cada etapa del pipeline
        $pipeline = Pipeline::where('is_default', true)->firstOrFail();
        $stages = PipelineStage::where('pipeline_id', $pipeline->id)->orderBy('position')->get()->keyBy('name');

        // Prospección (USD)
        if (isset($stages['Prospecto'])) {
            Opportunity::firstOrCreate(
                ['name' => 'Implementación ERP Cloud Inicial'],
                [
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stages['Prospecto']->id,
                    'company_id' => $compCorp1->id,
                    'contact_id' => $contactCorp1->id,
                    'owner_id' => $vendCorp->id,
                    'amount' => '15000.00',
                    'currency' => 'USD',
                    'probability' => 10,
                    'expected_close_date' => now()->addDays(45)->toDateString(),
                    'status' => 'open',
                ]
            );
        }

        // Calificación / Contactado (PEN)
        if (isset($stages['Contactado'])) {
            Opportunity::firstOrCreate(
                ['name' => 'Módulos de Facturación y Ventas'],
                [
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stages['Contactado']->id,
                    'company_id' => $compPyme1->id,
                    'contact_id' => $contactPyme1->id,
                    'owner_id' => $vendPyme->id,
                    'amount' => '45000.00',
                    'currency' => 'PEN',
                    'probability' => 20,
                    'expected_close_date' => now()->addDays(30)->toDateString(),
                    'status' => 'open',
                ]
            );
        }

        // Propuesta (USD)
        if (isset($stages['Propuesta'])) {
            Opportunity::firstOrCreate(
                ['name' => 'Migración de Infraestructura Servidores'],
                [
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stages['Propuesta']->id,
                    'company_id' => $compCorp1->id,
                    'contact_id' => $contactCorp1->id,
                    'owner_id' => $vendCorp->id,
                    'amount' => '28000.00',
                    'currency' => 'USD',
                    'probability' => 60,
                    'expected_close_date' => now()->addDays(20)->toDateString(),
                    'status' => 'open',
                ]
            );
        }

        // Negociación (EUR)
        if (isset($stages['Negociación'])) {
            Opportunity::firstOrCreate(
                ['name' => 'Suscripción Enterprise Anual'],
                [
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stages['Negociación']->id,
                    'company_id' => $compPyme1->id,
                    'contact_id' => $contactPyme1->id,
                    'owner_id' => $vendPyme->id,
                    'amount' => '12500.00',
                    'currency' => 'EUR',
                    'probability' => 80,
                    'expected_close_date' => now()->addDays(10)->toDateString(),
                    'status' => 'open',
                ]
            );
        }

        // Ganada (USD) - Usada para el flujo de cotización, venta y factura
        $oppWon = null;
        if (isset($stages['Ganada'])) {
            $oppWon = Opportunity::firstOrCreate(
                ['name' => 'Consultoría y Soporte Anual 24/7'],
                [
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stages['Ganada']->id,
                    'company_id' => $compCorp2->id,
                    'contact_id' => $contactCorp2->id,
                    'owner_id' => $vendCorp->id,
                    'amount' => '36000.00',
                    'currency' => 'USD',
                    'probability' => 100,
                    'expected_close_date' => now()->subDays(5)->toDateString(),
                    'status' => 'won',
                    'actual_close_date' => now()->subDays(5)->toDateString(),
                ]
            );
        }

        // 8. Flujo Comercial Completo (Cotización -> Venta -> Factura interna)
        if ($oppWon) {
            $prodConsult = Product::where('sku', 'CONSULT-01')->first();
            $prodLic = Product::where('sku', 'LIC-PRO-01')->first();

            // Cotización Aceptada
            $quote = Quote::firstOrCreate(
                ['number' => 'COT-DEMO-001'],
                [
                    'company_id' => $compCorp2->id,
                    'contact_id' => $contactCorp2->id,
                    'opportunity_id' => $oppWon->id,
                    'owner_id' => $vendCorp->id,
                    'status' => 'accepted',
                    'currency' => 'USD',
                    'issue_date' => now()->subDays(15)->toDateString(),
                    'valid_until' => now()->addDays(15)->toDateString(),
                    'subtotal' => '30000.00',
                    'discount_total' => '0.00',
                    'tax_total' => '5700.00',
                    'total' => '35700.00',
                    'accepted_at' => now()->subDays(6),
                    'notes' => 'Cotización demo para servicios profesionales anuales.',
                ]
            );

            if ($prodConsult && $quote->wasRecentlyCreated) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $prodConsult->id,
                    'position' => 1,
                    'description' => $prodConsult->name,
                    'unit' => 'hour',
                    'quantity' => '100.000',
                    'unit_price' => '150.00',
                    'discount_type' => 'none',
                    'discount_value' => '0.00',
                    'tax_rate' => '19.00',
                    'subtotal' => '15000.00',
                    'discount_amount' => '0.00',
                    'tax_amount' => '2850.00',
                    'total' => '17850.00',
                ]);

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'product_id' => $prodLic?->id,
                    'position' => 2,
                    'description' => $prodLic?->name ?? 'Licencia Anual',
                    'unit' => 'license',
                    'quantity' => '10.000',
                    'unit_price' => '1500.00',
                    'discount_type' => 'none',
                    'discount_value' => '0.00',
                    'tax_rate' => '19.00',
                    'subtotal' => '15000.00',
                    'discount_amount' => '0.00',
                    'tax_amount' => '2850.00',
                    'total' => '17850.00',
                ]);
            }

            // Venta Confirmada
            $sale = Sale::firstOrCreate(
                ['number' => 'VNT-DEMO-001'],
                [
                    'quote_id' => $quote->id,
                    'company_id' => $compCorp2->id,
                    'contact_id' => $contactCorp2->id,
                    'opportunity_id' => $oppWon->id,
                    'owner_id' => $vendCorp->id,
                    'status' => 'completed',
                    'currency' => 'USD',
                    'sale_date' => now()->subDays(5)->toDateString(),
                    'subtotal' => '30000.00',
                    'discount_total' => '0.00',
                    'tax_total' => '5700.00',
                    'total' => '35700.00',
                    'completed_at' => now()->subDays(4),
                    'notes' => 'Venta completada y aprobada por gerencia comercial.',
                ]
            );

            if ($prodConsult && $sale->wasRecentlyCreated) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $prodConsult->id,
                    'position' => 1,
                    'description' => $prodConsult->name,
                    'unit' => 'hour',
                    'quantity' => '100.000',
                    'unit_price' => '150.00',
                    'tax_rate' => '19.00',
                    'subtotal' => '15000.00',
                    'discount_amount' => '0.00',
                    'tax_amount' => '2850.00',
                    'total' => '17850.00',
                ]);
            }

            // Factura Interna Pagada (Documento Administrativo Interno)
            $invoice = Invoice::firstOrCreate(
                ['number' => 'FAC-DEMO-001'],
                [
                    'sale_id' => $sale->id,
                    'company_id' => $compCorp2->id,
                    'contact_id' => $contactCorp2->id,
                    'owner_id' => $vendCorp->id,
                    'status' => 'paid',
                    'currency' => 'USD',
                    'issue_date' => now()->subDays(4)->toDateString(),
                    'due_date' => now()->addDays(26)->toDateString(),
                    'subtotal' => '30000.00',
                    'discount_total' => '0.00',
                    'tax_total' => '5700.00',
                    'total' => '35700.00',
                    'paid_at' => now()->subDays(2),
                    'company_name' => $compCorp2->trade_name,
                    'company_tax_id' => $compCorp2->tax_id,
                    'contact_name' => $contactCorp2->full_name,
                    'contact_email' => $contactCorp2->email,
                    'notes' => 'Registro interno administrativo pagado vía transferencia verificada.',
                ]
            );

            if ($prodConsult && $invoice->wasRecentlyCreated) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $prodConsult->id,
                    'position' => 1,
                    'description' => $prodConsult->name,
                    'unit' => 'hour',
                    'quantity' => '100.000',
                    'unit_price' => '150.00',
                    'tax_rate' => '19.00',
                    'subtotal' => '15000.00',
                    'discount_amount' => '0.00',
                    'tax_amount' => '2850.00',
                    'total' => '17850.00',
                ]);
            }
        }

        // 9. Tickets de Soporte
        $catSoporte = TicketCategory::first();
        $ticketResuelto = Ticket::firstOrCreate(
            ['number' => 'TCK-DEMO-001'],
            [
                'company_id' => $compCorp1->id,
                'contact_id' => $contactCorp1->id,
                'requester_name' => 'Juan Pérez Ramos',
                'requester_email' => 'jperez@andinas.test',
                'assigned_to' => $soporte->id,
                'created_by' => $vendCorp->id,
                'category_id' => $catSoporte?->id,
                'subject' => 'Duda con configuración de sincronización nocturna',
                'description' => 'Cliente consulta el procedimiento para programar el cron de sincronización.',
                'status' => 'resolved',
                'priority' => 'medium',
                'channel' => 'email',
                'first_response_at' => now()->subDays(2)->addHours(1),
                'resolved_at' => now()->subDay(),
            ]
        );

        if ($ticketResuelto->wasRecentlyCreated) {
            TicketMessage::create([
                'ticket_id' => $ticketResuelto->id,
                'user_id' => $soporte->id,
                'type' => 'reply',
                'body' => 'Estimado Juan, le adjunto la instrucción para configurar el comando crm:remind-tasks en el programador de tareas.',
                'is_internal' => false,
            ]);
        }

        Ticket::firstOrCreate(
            ['number' => 'TCK-DEMO-002'],
            [
                'company_id' => $compPyme1->id,
                'contact_id' => $contactPyme1->id,
                'requester_name' => 'Carlos Mendoza',
                'requester_email' => 'cmendoza@lima-innova.test',
                'assigned_to' => $soporte->id,
                'created_by' => $vendPyme->id,
                'category_id' => $catSoporte?->id,
                'subject' => 'Solicitud de ampliación de usuarios demo',
                'description' => 'El cliente solicita verificar si se pueden añadir 2 cuentas adicionales al equipo.',
                'status' => 'open',
                'priority' => 'low',
                'channel' => 'web',
                'first_response_at' => null,
            ]
        );

        // 10. Tareas y Actividades
        Task::firstOrCreate(
            ['title' => 'Revisión trimestral de acuerdos comerciales'],
            [
                'description' => 'Reunión presencial para evaluar renovación de licencias.',
                'assigned_to' => $vendCorp->id,
                'created_by' => $supCorp->id,
                'status' => 'pending',
                'priority' => 'high',
                'due_at' => now()->addDays(5),
            ]
        );

        Task::firstOrCreate(
            ['title' => 'Llamada de seguimiento a Lead calificado'],
            [
                'description' => 'Contactar a Martín Beltrán de Cadena Hotelera del Valle para acordar demo.',
                'assigned_to' => $vendCorp->id,
                'created_by' => $vendCorp->id,
                'status' => 'pending',
                'priority' => 'medium',
                'due_at' => now()->addDays(2),
            ]
        );

        Activity::firstOrCreate(
            ['subject' => 'Videollamada de presentación de propuesta'],
            [
                'type' => 'meeting',
                'subjectable_type' => Company::class,
                'subjectable_id' => $compCorp1->id,
                'user_id' => $vendCorp->id,
                'status' => 'completed',
                'scheduled_at' => now()->subDays(3),
                'completed_at' => now()->subDays(3)->addHour(),
                'description' => 'El cliente estuvo muy interesado en la migración a nube.',
            ]
        );

        // 11. Campaña simulada
        Campaign::firstOrCreate(
            ['name' => 'Campaña Q4 Soluciones Cloud y Servicios'],
            [
                'description' => 'Optimice la gestión comercial y soporte en 2026',
                'status' => 'active',
                'type' => 'email',
                'owner_id' => $admin->id,
                'budget' => '3500.00',
            ]
        );
    }
}
