@extends('layouts.app', ['header' => 'Nuevo usuario', 'subheader' => 'Crear cuenta de usuario y asignar privilegios'])

@section('title', 'Nuevo usuario')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Usuarios', 'url' => route('admin.users.index')],
        ['label' => 'Nuevo'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-card title="Crear nuevo usuario" subtitle="Completa los datos del usuario y selecciona sus roles autorizados">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nombre completo *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico *</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Contraseña inicial *</label>
                    <input type="password" id="password" name="password" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Mínimo 8 caracteres.</p>
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
                                <option value="{{ $t->id }}" @selected((string) old('team_id') === (string) $t->id)>{{ $t->name }}</option>
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
                            <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Roles del sistema</label>
                    <div class="space-y-2 rounded-md border border-slate-200 p-3 bg-slate-50/50">
                        @foreach ($roles as $r)
                            <label class="flex items-start space-x-3 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $r->id }}"
                                    @checked(is_array(old('roles')) && in_array($r->id, old('roles'), false))
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
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Cancelar
                    </a>
                    <x-button type="submit">
                        Crear usuario
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
