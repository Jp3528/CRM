@extends('layouts.app', ['header' => 'Roles y permisos', 'subheader' => 'Matriz de privilegios funcionales y alcance operativo'])

@section('title', 'Roles y permisos')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Roles y permisos']]" />
@endsection

@section('content')
    <div class="space-y-6" x-data="{ activeRoleId: {{ $roles->first()?->id ?? 1 }} }">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        {{-- Barra de selección de roles reservados --}}
        <div class="flex overflow-x-auto gap-2 border-b border-slate-200 pb-2">
            @foreach ($roles as $r)
                <button type="button"
                    @click="activeRoleId = {{ $r->id }}"
                    :class="activeRoleId === {{ $r->id }}
                        ? 'bg-slate-900 text-white font-semibold'
                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-300 font-medium'"
                    class="rounded-md px-4 py-2 text-xs transition-colors shrink-0 flex items-center space-x-2">
                    <span>{{ $r->name }}</span>
                    @php
                        $scopeType = match ($r->name) {
                            'Superadministrador', 'Administrador' => 'Global',
                            'Gerente comercial', 'Supervisor' => 'Equipo',
                            default => 'Propio',
                        };
                    @endphp
                    <span class="rounded px-1.5 py-0.5 text-[10px] font-mono {{ in_array($r->name, ['Superadministrador', 'Administrador']) ? 'bg-blue-900/40 text-blue-200' : 'bg-slate-200 text-slate-800' }}">
                        {{ $scopeType }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Paneles de configuración por rol --}}
        @foreach ($roles as $r)
            <div x-show="activeRoleId === {{ $r->id }}" x-cloak style="display: none;">
                <x-card title="Rol: {{ $r->name }}" subtitle="{{ $r->description }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-md bg-slate-50 p-4 border border-slate-200 text-xs">
                        <div>
                            <p class="font-semibold text-slate-800">
                                Alcance de datos (DataScope):
                                <span class="font-mono text-slate-900">
                                    @if (in_array($r->name, ['Superadministrador', 'Administrador']))
                                        Global (Acceso a todos los registros del CRM)
                                    @elseif (in_array($r->name, ['Gerente comercial', 'Supervisor']))
                                        Equipo (Acceso a registros propios y de integrantes del mismo equipo)
                                    @else
                                        Propio (Acceso restringido únicamente a registros asignados)
                                    @endif
                                </span>
                            </p>
                            @if ($r->name === 'Consulta')
                                <p class="text-amber-800 font-medium mt-1">
                                    Aviso de seguridad: El rol Consulta es estrictamente de solo lectura. Incluso si se asignan permisos de escritura, el núcleo de autorización (Gate) los bloqueará.
                                </p>
                            @endif
                            @if ($r->name === 'Superadministrador')
                                <p class="text-blue-800 font-medium mt-1">
                                    El Superadministrador dispone de acceso total irrestricto y no puede ser despojado de permisos.
                                </p>
                            @endif
                        </div>

                        @if ($canUpdate && $r->name !== 'Superadministrador')
                            <form method="POST" action="{{ route('roles.reset', $r) }}" onsubmit="return confirm('¿Restablecer los permisos recomendados por defecto para el rol {{ $r->name }}?');">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                    Restablecer valores por defecto
                                </button>
                            </form>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('roles.update', $r) }}">
                        @csrf
                        @method('PUT')

                        @php
                            $assignedPermIds = $r->permissions->pluck('id')->all();
                        @endphp

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($permissionsByGroup as $groupName => $groupPerms)
                                <div class="rounded-md border border-slate-200 p-4 bg-white shadow-sm space-y-3">
                                    <h4 class="font-semibold text-xs uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2 flex justify-between items-center">
                                        <span>{{ ucfirst($groupName) }}</span>
                                        <span class="text-[11px] font-normal text-slate-400">{{ count($groupPerms) }} permisos</span>
                                    </h4>
                                    <div class="space-y-2">
                                        @foreach ($groupPerms as $perm)
                                            @php
                                                $isAssigned = in_array($perm->id, $assignedPermIds, false) || $r->name === 'Superadministrador';
                                            @endphp
                                            <label class="flex items-start space-x-2.5 text-xs {{ $r->name === 'Superadministrador' || ! $canUpdate ? 'cursor-not-allowed opacity-80' : 'cursor-pointer' }}">
                                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                                    @checked($isAssigned)
                                                    @disabled($r->name === 'Superadministrador' || ! $canUpdate)
                                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-500 mt-0.5">
                                                <div>
                                                    <span class="font-medium text-slate-800">{{ $perm->label ?: $perm->name }}</span>
                                                    <span class="block font-mono text-[10px] text-slate-400">{{ $perm->name }}</span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($canUpdate && $r->name !== 'Superadministrador')
                            <div class="mt-6 flex items-center justify-end border-t border-slate-200 pt-4 space-x-3">
                                <x-button type="submit">
                                    Guardar permisos para {{ $r->name }}
                                </x-button>
                            </div>
                        @endif
                    </form>
                </x-card>
            </div>
        @endforeach
    </div>
@endsection
