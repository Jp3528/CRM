@extends('layouts.app', ['header' => $team->name, 'subheader' => 'Ficha de equipo y miembros'])

@section('title', 'Equipo: ' . $team->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Equipos', 'url' => route('teams.index')],
        ['label' => $team->name],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        @if ($errors->any())
            <x-flash type="error">{{ $errors->first() }}</x-flash>
        @endif

        {{-- Información general del equipo --}}
        <x-card title="Información del equipo">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="font-medium text-slate-500">Nombre</dt>
                    <dd class="mt-1 text-slate-900 font-semibold">{{ $team->name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Slug</dt>
                    <dd class="mt-1 font-mono text-xs text-slate-600">{{ $team->slug }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Estado</dt>
                    <dd class="mt-1"><x-status-badge :status="$team->status" /></dd>
                </div>
                <div class="sm:col-span-3">
                    <dt class="font-medium text-slate-500">Descripción</dt>
                    <dd class="mt-1 text-slate-700">{{ $team->description ?: 'Sin descripción registrada.' }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                <a href="{{ route('teams.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    ← Volver a equipos
                </a>
                <div class="flex items-center space-x-3">
                    @if ($canUpdate)
                        <a href="{{ route('teams.edit', $team) }}" class="inline-flex items-center rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-700">
                            Editar equipo
                        </a>
                    @endif
                    @if ($canDelete && $members->isEmpty())
                        <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('¿Confirmas eliminar este equipo?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-red-700">
                                Eliminar equipo
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Formulario para asignar un nuevo miembro al equipo --}}
        @if ($canAssign && $candidates->isNotEmpty())
            <x-card title="Asignar miembro al equipo" subtitle="Agrega un usuario activo a la estructura de este equipo">
                <form method="POST" action="{{ route('teams.members.store', $team) }}" class="flex flex-col sm:flex-row gap-3 items-end">
                    @csrf
                    <div class="flex-1 w-full">
                        <label for="user_id" class="block text-xs font-medium text-slate-700 mb-1">Seleccionar usuario *</label>
                        <select id="user_id" name="user_id" required
                            class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                            <option value="">-- Seleccionar usuario activo --</option>
                            @foreach ($candidates as $cand)
                                <option value="{{ $cand->id }}">
                                    {{ $cand->name }} ({{ $cand->email }})
                                    @if ($cand->team_id) [Actualmente en otro equipo] @else [Sin equipo] @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <x-button type="submit">
                        Asignar al equipo
                    </x-button>
                </form>
                <p class="mt-2 text-xs text-slate-500">
                    Al asignar un usuario al equipo, el alcance de los registros comerciales vinculados al propietario se traslada al equipo inmediatamente.
                </p>
            </x-card>
        @endif

        {{-- Listado de miembros del equipo --}}
        <x-card title="Miembros asignados ({{ $members->count() }})" subtitle="Usuarios que integran actualmente este equipo comercial">
            @if ($members->isEmpty())
                <p class="text-sm text-slate-500 py-4 text-center">Este equipo no tiene miembros asignados en este momento.</p>
            @else
                <div class="overflow-x-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Usuario</th>
                                <th class="px-4 py-3 text-left">Roles</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($members as $m)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        <a href="{{ route('admin.users.show', $m) }}" class="font-semibold hover:underline">{{ $m->name }}</a>
                                        <span class="block text-xs font-normal text-slate-500">{{ $m->email }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($m->roles as $role)
                                                <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
                                                    {{ $role->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-slate-400">Sin rol</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :status="$m->status" />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($canAssign)
                                            <form method="POST" action="{{ route('teams.members.destroy', ['team' => $team, 'user' => $m]) }}" class="inline"
                                                onsubmit="return confirm('¿Retirar a {{ $m->name }} de este equipo? Su alcance volverá a propio.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800 underline">
                                                    Retirar del equipo
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
