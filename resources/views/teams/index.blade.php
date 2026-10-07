@extends('layouts.app', ['header' => 'Equipos', 'subheader' => 'Estructura comercial y alcance de datos por equipo'])

@section('title', 'Equipos')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Equipos']]" />
@endsection

@section('content')
    <x-card title="Equipos de trabajo" subtitle="{{ $teams->total() }} equipo(s) registrado(s)">
        @if (session('status'))
            <div class="mb-4">
                <x-flash type="success">{{ session('status') }}</x-flash>
            </div>
        @endif

        <form method="GET" action="{{ route('teams.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar por nombre o slug…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-2 lg:col-span-6">

            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-3">
                <option value="">Todos los estados</option>
                <option value="active" @selected($filters['status'] === 'active')>Activos</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactivos</option>
            </select>

            <div class="col-span-full flex flex-wrap items-center gap-2 pt-1">
                <x-button>Buscar</x-button>
                <a href="{{ route('teams.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if ($canCreate)
                    <a href="{{ route('teams.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo equipo</a>
                @endif
            </div>
        </form>

        @if ($teams->isEmpty())
            <x-empty-state title="No se encontraron equipos." message="Crea el primer equipo comercial o ajusta tus filtros."
                :action-url="$canCreate ? route('teams.create') : null" action-label="Nuevo equipo" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Equipo</th>
                            <th class="px-4 py-3 text-left">Slug</th>
                            <th class="px-4 py-3 text-center">Miembros activos</th>
                            <th class="px-4 py-3 text-center">Total miembros</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($teams as $t)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <a href="{{ route('teams.show', $t) }}" class="font-semibold hover:underline">{{ $t->name }}</a>
                                    @if ($t->description)
                                        <span class="block text-xs font-normal text-slate-500 truncate max-w-sm">{{ $t->description }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $t->slug }}</td>
                                <td class="px-4 py-3 text-center font-medium text-slate-800">{{ $t->active_members_count }}</td>
                                <td class="px-4 py-3 text-center text-slate-500">{{ $t->total_members_count }}</td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$t->status" />
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="{{ route('teams.show', $t) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">
                                        Ver miembros
                                    </a>
                                    @can('update', $t)
                                        <a href="{{ route('teams.edit', $t) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 underline">
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
                {{ $teams->links() }}
            </div>
        @endif
    </x-card>
@endsection
