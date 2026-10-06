@extends('layouts.app', ['header' => $user->name, 'subheader' => $user->email])

@section('title', 'Usuario: ' . $user->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Usuarios', 'url' => route('admin.users.index')],
        ['label' => $user->name],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        {{-- Tarjetas KPI de registros vinculados al usuario --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Empresas</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['companies'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Contactos</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['contacts'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Leads</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['leads'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Oportunidades</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['opportunities'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Tareas</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['tasks'] }}</p>
            </div>
        </div>

        <x-card title="Ficha de usuario">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="font-medium text-slate-500">Nombre completo</dt>
                    <dd class="mt-1 text-slate-900 font-semibold">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Correo electrónico</dt>
                    <dd class="mt-1 text-slate-900">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Estado de cuenta</dt>
                    <dd class="mt-1"><x-status-badge :status="$user->status" /></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Equipo comercial</dt>
                    <dd class="mt-1 text-slate-900">
                        @if ($user->team)
                            <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                {{ $user->team->name }}
                            </span>
                        @else
                            <span class="text-slate-400">Sin equipo</span>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-slate-500 mb-1">Roles asignados</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        @forelse ($user->roles as $role)
                            <span class="inline-flex items-center rounded bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-800">
                                {{ $role->name }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">Sin roles asignados</span>
                        @endforelse
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Fecha de registro</dt>
                    <dd class="mt-1 text-xs text-slate-600">{{ $user->created_at->format('d/m/Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Último acceso</dt>
                    <dd class="mt-1 text-xs text-slate-600">{{ $user->last_login_at?->format('d/m/Y H:i:s') ?? 'Nunca' }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    ← Volver a usuarios
                </a>
                <div class="flex items-center space-x-3">
                    @if ($canUpdate)
                        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-700">
                            Editar usuario
                        </a>
                    @endif
                    @if ($canDelete)
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Estás seguro de eliminar este usuario? Sus registros comerciales permanecerán intactos.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-red-700">
                                Eliminar usuario
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>
    </div>
@endsection
