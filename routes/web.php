<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationReportController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignMemberController;
use App\Http\Controllers\CampaignReportController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceReportController;
use App\Http\Controllers\InvoiceStatusController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadConversionController;
use App\Http\Controllers\LeadReportController;
use App\Http\Controllers\MessageTemplateController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OpportunityStageController;
use App\Http\Controllers\PipelineReportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteReportController;
use App\Http\Controllers\QuoteStatusController;
use App\Http\Controllers\QuoteToSaleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleStatusController;
use App\Http\Controllers\SaleToInvoiceController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\SupportReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\TicketStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NexusCRM Fase 9 — Tickets de soporte (Policies por módulo)
*/

// Landing pública mínima (conserva GET / 200 de Fase 1).
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

// ---------------- Guest ----------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

// ---------------- Auth ----------------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');

    // Primer módulo CRM funcional (Fase 3). Autorización vía Policies.
    Route::resource('companies', CompanyController::class);
    Route::delete('companies/{company}/tags/{tag}', [CompanyController::class, 'detachTag'])
        ->name('companies.tags.detach');

    Route::resource('contacts', ContactController::class);
    Route::delete('contacts/{contact}/tags/{tag}', [ContactController::class, 'detachTag'])
        ->name('contacts.tags.detach');

    // Leads + calificación + conversión transaccional (Fase 4).
    Route::resource('leads', LeadController::class);
    Route::delete('leads/{lead}/tags/{tag}', [LeadController::class, 'detachTag'])
        ->name('leads.tags.detach');
    Route::patch('leads/{lead}/qualify', [LeadController::class, 'qualify'])
        ->name('leads.qualify');
    Route::get('leads/{lead}/convert', [LeadConversionController::class, 'create'])
        ->name('leads.convert');
    Route::post('leads/{lead}/convert', [LeadConversionController::class, 'store'])
        ->name('leads.convert.store');

    // Oportunidades + pipeline comercial + Kanban (Fase 5). Kanban antes del
    // resource para que {opportunity} no capture "kanban".
    Route::get('opportunities/kanban', [OpportunityController::class, 'kanban'])
        ->name('opportunities.kanban');
    Route::resource('opportunities', OpportunityController::class);
    Route::delete('opportunities/{opportunity}/tags/{tag}', [OpportunityController::class, 'detachTag'])
        ->name('opportunities.tags.detach');
    Route::patch('opportunities/{opportunity}/stage', [OpportunityStageController::class, 'update'])
        ->name('opportunities.stage.update');

    // Tareas + actividades + calendario comercial (Fase 6).
    Route::resource('tasks', TaskController::class);
    Route::patch('tasks/{task}/complete', [TaskStatusController::class, 'complete'])
        ->name('tasks.complete');
    Route::patch('tasks/{task}/reopen', [TaskStatusController::class, 'reopen'])
        ->name('tasks.reopen');
    Route::patch('tasks/{task}/cancel', [TaskStatusController::class, 'cancel'])
        ->name('tasks.cancel');

    Route::resource('activities', ActivityController::class);

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // Tickets de soporte (Fase 9).
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])
        ->name('tickets.messages.store');
    Route::patch('tickets/{ticket}/open', [TicketStatusController::class, 'open'])
        ->name('tickets.open');
    Route::patch('tickets/{ticket}/pending', [TicketStatusController::class, 'pending'])
        ->name('tickets.pending');
    Route::patch('tickets/{ticket}/resolve', [TicketStatusController::class, 'resolve'])
        ->name('tickets.resolve');
    Route::patch('tickets/{ticket}/reopen', [TicketStatusController::class, 'reopen'])
        ->name('tickets.reopen');
    Route::patch('tickets/{ticket}/close', [TicketStatusController::class, 'close'])
        ->name('tickets.close');
    Route::get('tickets/{ticket}/print', [TicketController::class, 'print'])
        ->name('tickets.print');

    // Productos + cotizaciones (Fase 7).
    Route::resource('products', ProductController::class);
    Route::resource('quotes', QuoteController::class);
    Route::get('quotes/{quote}/print', [QuoteController::class, 'print'])
        ->name('quotes.print');
    Route::patch('quotes/{quote}/send', [QuoteStatusController::class, 'send'])
        ->name('quotes.send');
    Route::patch('quotes/{quote}/accept', [QuoteStatusController::class, 'accept'])
        ->name('quotes.accept');
    Route::patch('quotes/{quote}/reject', [QuoteStatusController::class, 'reject'])
        ->name('quotes.reject');

    // Ventas + facturación interna (Fase 8). Conversión/impresión antes de
    // los resources para que {sale}/{invoice} no capturen esas rutas.
    Route::get('sales/{sale}/invoice/create', [SaleToInvoiceController::class, 'create'])
        ->name('sales.invoice.create');
    Route::post('sales/{sale}/invoice', [SaleToInvoiceController::class, 'store'])
        ->name('sales.invoice.store');
    Route::resource('sales', SaleController::class);
    Route::get('sales/{sale}/print', [SaleController::class, 'print'])
        ->name('sales.print');
    Route::patch('sales/{sale}/confirm', [SaleStatusController::class, 'confirm'])
        ->name('sales.confirm');
    Route::patch('sales/{sale}/complete', [SaleStatusController::class, 'complete'])
        ->name('sales.complete');
    Route::patch('sales/{sale}/cancel', [SaleStatusController::class, 'cancel'])
        ->name('sales.cancel');

    Route::get('quotes/{quote}/sale/create', [QuoteToSaleController::class, 'create'])
        ->name('quotes.sale.create');
    Route::post('quotes/{quote}/sale', [QuoteToSaleController::class, 'store'])
        ->name('quotes.sale.store');

    Route::resource('invoices', InvoiceController::class)->except(['create', 'store', 'edit', 'update']);
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])
        ->name('invoices.print');
    Route::patch('invoices/{invoice}/send', [InvoiceStatusController::class, 'send'])
        ->name('invoices.send');
    Route::patch('invoices/{invoice}/pay', [InvoiceStatusController::class, 'pay'])
        ->name('invoices.pay');
    Route::patch('invoices/{invoice}/cancel', [InvoiceStatusController::class, 'cancel'])
        ->name('invoices.cancel');

    // Campañas + comunicaciones (Fase 10). Todo envío es interno/simulado,
    // sin proveedor externo (email/SMS/WhatsApp solo como clasificación).
    Route::resource('campaigns', CampaignController::class);
    Route::get('campaigns/{campaign}/audience', [CampaignMemberController::class, 'audience'])
        ->name('campaigns.audience');
    Route::post('campaigns/{campaign}/members', [CampaignMemberController::class, 'store'])
        ->name('campaigns.members.store');
    Route::post('campaigns/{campaign}/members/bulk', [CampaignMemberController::class, 'bulkStore'])
        ->name('campaigns.members.bulk');
    Route::delete('campaigns/{campaign}/members/{member}', [CampaignMemberController::class, 'destroy'])
        ->name('campaigns.members.destroy');
    Route::patch('campaigns/{campaign}/members/{member}/unsubscribe', [CampaignMemberController::class, 'unsubscribe'])
        ->name('campaigns.members.unsubscribe');
    Route::post('campaigns/{campaign}/communications', [CommunicationController::class, 'bulkStore'])
        ->name('campaigns.communications.bulk');

    Route::resource('templates', MessageTemplateController::class)
        ->parameters(['templates' => 'messageTemplate']);

    Route::resource('communications', CommunicationController::class);
    Route::patch('communications/{communication}/simulate', [CommunicationController::class, 'simulate'])
        ->name('communications.simulate');

    // Motor de automatizaciones internas (Fase 11). Event-driven, sin colas,
    // sin webhooks ni envíos externos. Estado solo vía activate/pause.
    Route::resource('automations', AutomationController::class);
    Route::post('automations/{automation}/activate', [AutomationController::class, 'activate'])
        ->name('automations.activate');
    Route::post('automations/{automation}/pause', [AutomationController::class, 'pause'])
        ->name('automations.pause');
    Route::post('automations/{automation}/dry-run', [AutomationController::class, 'dryRun'])
        ->name('automations.dry-run');
    Route::get('automations/{automation}/runs/{run}', [AutomationController::class, 'showRun'])
        ->name('automations.runs.show');

    // Reportes ejecutivos + forecast (Fase 12). Solo lectura, DataScope en
    // cada query, montos por moneda (sin FX). Sin exports (Fase 13).
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sales', SalesReportController::class)->name('reports.sales');
    Route::get('reports/pipeline', PipelineReportController::class)->name('reports.pipeline');
    Route::get('reports/leads', LeadReportController::class)->name('reports.leads');
    Route::get('reports/quotes', QuoteReportController::class)->name('reports.quotes');
    Route::get('reports/invoices', InvoiceReportController::class)->name('reports.invoices');
    Route::get('reports/support', SupportReportController::class)->name('reports.support');
    Route::get('reports/campaigns', CampaignReportController::class)->name('reports.campaigns');
    Route::get('reports/automations', AutomationReportController::class)->name('reports.automations');
    Route::get('forecast', ForecastController::class)->name('forecast.index');

    // Importaciones CSV de datos (Fase 13). Asistente, plantilla, mapeo, preview y confirmación.
    Route::get('imports', [DataImportController::class, 'index'])->name('imports.index');
    Route::get('imports/create', [DataImportController::class, 'create'])->name('imports.create');
    Route::get('imports/template/{module}', [DataImportController::class, 'template'])->name('imports.template');
    Route::post('imports/upload', [DataImportController::class, 'upload'])->name('imports.upload');
    Route::post('imports/preview', [DataImportController::class, 'preview'])->name('imports.preview');
    Route::post('imports/confirm', [DataImportController::class, 'confirm'])->name('imports.confirm');
    Route::get('imports/{import}', [DataImportController::class, 'show'])->name('imports.show');
    Route::get('imports/{import}/errors', [DataImportController::class, 'errors'])->name('imports.errors');

    // Exportaciones CSV de datos (Fase 13). Streaming con UTF-8 BOM, DataScope y sanitización de fórmulas.
    Route::get('exports/{module}', [ExportController::class, 'export'])->name('exports.module');

    // Placeholders controlados de módulos futuros (sin CRUD, vista "próximamente").
    foreach ([
        'teams' => 'Equipos',
        'roles' => 'Roles y permisos',
        'settings' => 'Configuración',
    ] as $key => $label) {
        Route::get("/{$key}", fn () => response()->view('coming-soon', [
            'module' => $label,
        ]))->name("{$key}.index");
    }

    // ---------------- Admin (autorización backend real) ----------------
    // Ejemplo operativo: solo users.view (o Superadministrador vía Gate::before).
    Route::get('/admin/users', function () {
        return view('admin.users-placeholder', [
            'total' => \App\Models\User::count(),
        ]);
    })->middleware('permission:users.view')->name('admin.users.index');
});
