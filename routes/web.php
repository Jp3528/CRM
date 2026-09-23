<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NexusCRM Fase 2 — Rutas base de acceso y navegación
|--------------------------------------------------------------------------
| guest        : login + recuperación (arquitectura lista, mailer log/array)
| auth+active  : dashboard, perfil, placeholders de módulos (sin CRUD)
| admin        : ejemplo de autorización backend real (users.view)
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

    // Placeholders controlados de módulos futuros (sin CRUD, vista "próximamente").
    foreach ([
        'companies' => 'Empresas',
        'contacts' => 'Contactos',
        'leads' => 'Leads',
        'opportunities' => 'Oportunidades',
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
