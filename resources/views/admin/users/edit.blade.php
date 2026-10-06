@extends('layouts.app', ['header' => 'Editar usuario', 'subheader' => $user->name])

@section('title', 'Editar: ' . $user->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Usuarios', 'url' => route('admin.users.index')],
        ['label' => $user->name, 'url' => route('admin.users.show', $user)],
        ['label' => 'Editar'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-card title="Modificar usuario" subtitle="Actualiza los datos del usuario, equipo o roles de acceso">
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nombre completo *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico *</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Nueva contraseña (opcional)</label>
                    <input type="password" id="password" name="password"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Dejar en blanco para conservar la contraseña actual.</p>
                    @error('password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="team_id" class="block text-sm font-medium text-slate-700">Equipo comercial</label>
                        <select id="team_id" name="team_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                            <option value="">-- Sin equipo asignado --</option>
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected((string) old('team_id', $user->team_id) === (string) $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('team_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Estado de cuenta *</label>
                        <select id="status" name="status" required
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                            <option value="active" @selected(old('status', $user->status) === 'active')>Activo</option>
                            <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactivo</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Roles del sistema</label>
                    @php
                        $userRoleIds = old('roles', $user->roles->pluck('id')->all());
                    @endphp
                    <div class="space-y-2 rounded-md border border-slate-200 p-3 bg-slate-50/50">
                        @foreach ($roles as $r)
                            <label class="flex items-start space-x-3 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $r->id }}"
                                    @checked(is_array($userRoleIds) && in_array($r->id, $userRoleIds, false))
                                    class="rounded border-slate-300 text-slate-900 focus:ring-slate-500 mt-0.5">
                                <div>
                                    <span class="font-medium text-slate-800">{{ $r->name }}</span>
                                    @if ($r->description)
                                        <p class="text-xs text-slate-500">{{ $r->description }}</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('roles')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Cancelar
                    </a>
                    <x-button type="submit">
                        Guardar cambios
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
