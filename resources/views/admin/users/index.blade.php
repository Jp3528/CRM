@extends('layouts.app', ['header' => 'Usuarios', 'subheader' => 'Administración y control de acceso'])

@section('title', 'Usuarios')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Usuarios']]" />
@endsection

@section('content')
    <x-card title="Gestión de usuarios" subtitle="{{ $users->total() }} usuario(s) registrado(s)">
        @if (session('status'))
            <div class="mb-4">
                <x-flash type="success">{{ session('status') }}</x-flash>
            </div>
        @endif

        <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar por nombre o correo…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-2 lg:col-span-4">
            
            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todos los estados</option>
                <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
            </select>

            <select name="team_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-3">
                <option value="">Todos los equipos</option>
                <option value="none" @selected($filters['team_id'] === 'none')>Sin equipo</option>
                @foreach ($teams as $t)
                    <option value="{{ $t->id }}" @selected((string)$filters['team_id'] === (string)$t->id)>{{ $t->name }}</option>
                @endforeach
            </select>

            <select name="role_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-3">
                <option value="">Todos los roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->id }}" @selected((string)$filters['role_id'] === (string)$r->id)>{{ $r->name }}</option>
                @endforeach
            </select>

            <div class="col-span-full flex flex-wrap items-center gap-2 pt-1">
                <x-button>Buscar</x-button>
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\User::class)
                    <a href="{{ route('admin.users.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo usuario</a>
                @endcan
            </div>
        </form>

        @if ($users->isEmpty())
            <x-empty-state title="No se encontraron usuarios." message="Crea el primer usuario o ajusta los filtros de búsqueda."
                :action-url="auth()->user()->can('create', App\Models\User::class) ? route('admin.users.create') : null" action-label="Nuevo usuario" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Usuario</th>
                            <th class="px-4 py-2 text-left">Equipo</th>
                            <th class="px-4 py-2 text-left">Roles</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($users as $u)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('admin.users.show', $u) }}" class="hover:underline font-semibold">{{ $u->name }}</a>
                                    <span class="block text-xs font-normal text-slate-500">{{ $u->email }}</span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $u->team?->name ?? 'Sin equipo' }}
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($u->roles as $role)
                                            <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-slate-400">Sin rol</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <x-status-badge :status="$u->status" />
                                </td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('admin.users.show', $u) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">
                                        Ver
                                    </a>
                                    @can('update', $u)
                                        <a href="{{ route('admin.users.edit', $u) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 underline">
                                            Editar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $users->links() }}
            </div>
        @endif
    </x-card>
@endsection
