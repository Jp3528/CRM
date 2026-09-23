@extends('layouts.app', ['header' => 'Mi perfil', 'subheader' => 'Datos de cuenta y seguridad'])

@section('title', 'Mi perfil')

@section('content')
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos personales" subtitle="Nombre, correo, equipo y roles">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Equipo</dt><dd class="font-medium">{{ $user->team?->name ?? 'Sin equipo' }}</dd></div>
                <div><dt class="text-slate-500">Roles</dt><dd class="mt-1 flex flex-wrap gap-1">@foreach ($user->roles as $role)<x-badge>{{ $role->name }}</x-badge>@endforeach</dd></div>
            </dl>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <x-label for="name" value="Nombre" />
                    <input id="name" name="name" type="text" required value="{{ old('name', $user->name) }}"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <x-input-error :message="$errors->get('name')[0] ?? null" />
                </div>
                <div>
                    <x-label for="email" value="Correo electrónico" />
                    <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <x-input-error :message="$errors->get('email')[0] ?? null" />
                </div>
                <x-button>Guardar cambios</x-button>
            </form>
        </x-card>

        <x-card title="Cambiar contraseña" subtitle="Se requiere la contraseña actual">
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <x-label for="current_password" value="Contraseña actual" />
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <x-input-error :message="$errors->get('current_password')[0] ?? null" />
                </div>
                <div>
                    <x-label for="password" value="Nueva contraseña" />
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                    <x-input-error :message="$errors->get('password')[0] ?? null" />
                </div>
                <div>
                    <x-label for="password_confirmation" value="Confirmar nueva contraseña" />
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                </div>
                <x-button>Actualizar contraseña</x-button>
            </form>
        </x-card>
    </div>
@endsection
