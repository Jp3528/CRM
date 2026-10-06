@extends('layouts.app', ['header' => 'Nuevo pipeline comercial', 'subheader' => 'Creación de un nuevo flujo de etapas de oportunidad'])

@section('title', 'Nuevo pipeline comercial')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Pipelines', 'url' => route('settings.pipelines.index')],
        ['label' => 'Nuevo'],
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

        <x-card title="Detalles del pipeline">
            <form action="{{ route('settings.pipelines.store') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <x-label for="name" value="Nombre del pipeline *" />
                    <x-input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full" placeholder="Ej: Ventas Corporativas B2B" />
                </div>

                <div>
                    <x-label for="description" value="Descripción" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm" placeholder="Breve descripción del proceso comercial...">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label for="status" value="Estado *" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm text-sm">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="flex items-center space-x-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                            <span>Designar como predeterminado</span>
                        </label>
                    </div>
                </div>

                <p class="text-xs text-slate-500 bg-slate-50 p-3 rounded border border-slate-200">
                    Al crearse se configurarán automáticamente 4 etapas base: Prospección (10%), Propuesta (50%), Ganada (100%) y Perdida (0%). Podrás personalizar, agregar o ajustar probabilidades después.
                </p>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('settings.pipelines.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">
                        Cancelar
                    </a>
                    <button type="submit" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Crear pipeline
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
