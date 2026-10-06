@extends('layouts.app', ['header' => 'Nuevo equipo', 'subheader' => 'Crear equipo comercial'])

@section('title', 'Nuevo equipo')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Equipos', 'url' => route('teams.index')],
        ['label' => 'Nuevo'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-xl">
        <x-card title="Crear equipo comercial" subtitle="Define el nombre y las características del nuevo equipo">
            <form method="POST" action="{{ route('teams.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nombre del equipo *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                        placeholder="Ej. Ventas Corporativas"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700">Slug identificador</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}"
                        placeholder="Opcional: se genera automáticamente si se deja vacío"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Solo letras, números y guiones. Ej: ventas-corporativas</p>
                    @error('slug')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Descripción</label>
                    <textarea id="description" name="description" rows="3"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Estado *</label>
                    <select id="status" name="status" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('teams.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Cancelar
                    </a>
                    <x-button type="submit">
                        Crear equipo
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
