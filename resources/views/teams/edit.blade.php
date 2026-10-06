@extends('layouts.app', ['header' => 'Editar equipo', 'subheader' => $team->name])

@section('title', 'Editar: ' . $team->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Equipos', 'url' => route('teams.index')],
        ['label' => $team->name, 'url' => route('teams.show', $team)],
        ['label' => 'Editar'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-xl">
        <x-card title="Modificar equipo" subtitle="Actualiza los datos del equipo comercial">
            <form method="POST" action="{{ route('teams.update', $team) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nombre del equipo *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $team->name) }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700">Slug identificador *</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug', $team->slug) }}" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                    @error('slug')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700">Descripción</label>
                    <textarea id="description" name="description" rows="3"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">{{ old('description', $team->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Estado *</label>
                    <select id="status" name="status" required
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                        <option value="active" @selected(old('status', $team->status) === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status', $team->status) === 'inactive')>Inactivo</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('teams.show', $team) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
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
