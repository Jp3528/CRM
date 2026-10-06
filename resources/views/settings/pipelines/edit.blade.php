@extends('layouts.app', ['header' => "Editar: {$pipeline->name}", 'subheader' => 'Modificación de parámetros del pipeline'])

@section('title', "Editar pipeline: {$pipeline->name}")

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Pipelines', 'url' => route('settings.pipelines.index')],
        ['label' => $pipeline->name, 'url' => route('settings.pipelines.show', $pipeline)],
        ['label' => 'Editar'],
    ]" />
@endsection

@section('content')
    <div class="max-w-2xl space-y-6">
        @if ($errors->any())
            <x-flash type="danger">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </x-flash>
        @endif

        <x-card title="Modificar pipeline">
            <form action="{{ route('settings.pipelines.update', $pipeline) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <x-label for="name" value="Nombre del pipeline *" />
                    <x-input id="name" name="name" type="text" value="{{ old('name', $pipeline->name) }}" required class="mt-1 w-full" />
                </div>

                <div>
                    <x-label for="description" value="Descripción" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">{{ old('description', $pipeline->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="status" value="Estado *" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm text-sm">
                            <option value="active" {{ old('status', $pipeline->status) === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="inactive" {{ old('status', $pipeline->status) === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center space-x-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" {{ old('is_default', $pipeline->is_default) ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                            <span>Designar como predeterminado</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('settings.pipelines.show', $pipeline) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">
                        Cancelar
                    </a>
                    <button type="submit" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Guardar cambios
                    </button>
                </div>
            </form>

            @if ($pipeline->opportunities()->count() === 0 && !$pipeline->is_default)
                <div class="mt-8 pt-4 border-t border-slate-200 flex justify-end">
                    <form action="{{ route('settings.pipelines.destroy', $pipeline) }}" method="POST" onsubmit="return confirm('¿Eliminar este pipeline y sus etapas?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                            Eliminar pipeline
                        </button>
                    </form>
                </div>
            @endif
        </x-card>
    </div>
@endsection
