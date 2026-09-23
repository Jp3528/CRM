<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadConversionController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\OpportunityStageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NexusCRM Fase 5 — Oportunidades + pipeline + Kanban (Policies por módulo)
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

    // Placeholders controlados de módulos futuros (sin CRUD, vista "próximamente").
    foreach ([
        'tasks' => 'Tareas',
        'activities' => 'Actividades',
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
