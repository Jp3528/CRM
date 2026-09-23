@extends('layouts.app', ['header' => 'Dashboard', 'subheader' => 'Resumen de tu sesión'])

@section('title', 'Dashboard')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard']]" />
@endsection

@section('content')
    <x-card title="Hola, {{ $user->name }}" subtitle="{{ $now->format('l, d M Y · H:i') }}">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Rol principal</dt>
                <dd class="mt-1 font-medium">{{ $primaryRole?->name ?? 'Sin rol' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Equipo</dt>
                <dd class="mt-1 font-medium">{{ $user->team?->name ?? 'Sin equipo' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Correo</dt>
                <dd class="mt-1 font-medium">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Estado</dt>
                <dd class="mt-1"><x-badge color="{{ $user->isActive() ? 'green' : 'red' }}">{{ $user->status }}</x-badge></dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($user->roles as $role)<x-badge>{{ $role->name }}</x-badge>@endforeach
        </div>
    </x-card>

    <div class="grid gap-4 md:grid-cols-2">
        <x-card title="Accesos rápidos" subtitle="Solo lo que tus permisos permiten">
            <ul class="space-y-2 text-sm">
                <li><a class="text-slate-700 hover:underline" href="{{ route('dashboard') }}">· Dashboard</a></li>
                @can('companies.view')<li><a class="text-slate-700 hover:underline" href="{{ route('companies.index') }}">· Empresas (próximamente)</a></li>@endcan
                @can('contacts.view')<li><a class="text-slate-700 hover:underline" href="{{ route('contacts.index') }}">· Contactos (próximamente)</a></li>@endcan
                @can('leads.view')<li><a class="text-slate-700 hover:underline" href="{{ route('leads.index') }}">· Leads (próximamente)</a></li>@endcan
                @can('opportunities.view')<li><a class="text-slate-700 hover:underline" href="{{ route('opportunities.index') }}">· Oportunidades (próximamente)</a></li>@endcan
                @can('tasks.view')<li><a class="text-slate-700 hover:underline" href="{{ route('tasks.index') }}">· Tareas (próximamente)</a></li>@endcan
                @can('users.view')<li><a class="text-slate-700 hover:underline" href="{{ route('admin.users.index') }}">· Usuarios</a></li>@endcan
                <li><a class="text-slate-700 hover:underline" href="{{ route('profile.edit') }}">· Mi perfil</a></li>
            </ul>
        </x-card>

        <x-card title="Estado del sistema" subtitle="Base operativa de Fase 2">
            <ul class="space-y-2 text-sm text-slate-600">
                <li>· Autenticación web activa con regeneración de sesión.</li>
                <li>· RBAC operativo (roles, permisos directos, Superadministrador).</li>
                <li>· Autorización backend en rutas y Gates; el sidebar solo oculta enlaces.</li>
                <li>· Módulos comerciales pendientes (ver placeholders).</li>
            </ul>
        </x-card>
    </div>
@endsection
