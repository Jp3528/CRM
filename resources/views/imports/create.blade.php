@extends('layouts.app', ['header' => 'Nueva importación', 'subheader' => 'Paso 1: Selección de módulo y carga de archivo'])

@section('title', 'Nueva importación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Importaciones', 'url' => route('imports.index')],
        ['label' => 'Nueva importación'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-3xl">
        {{-- Indicador de pasos --}}
        <div class="mb-6 flex items-center justify-between border-b border-slate-200 pb-4">
            <div class="flex items-center space-x-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">1</span>
                <span class="text-sm font-semibold text-slate-900">Archivo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-200 mx-4"></div>
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">2</span>
                <span class="text-sm font-medium">Mapeo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-200 mx-4"></div>
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">3</span>
                <span class="text-sm font-medium">Previsualización</span>
            </div>
        </div>

        <x-card title="Paso 1: Carga de archivo CSV" subtitle="Selecciona el módulo destino y adjunta el archivo CSV a importar">
            <form method="POST" action="{{ route('imports.upload') }}" enctype="multipart/form-data" class="space-y-6" x-data="{ selectedModule: '{{ array_key_first($modules) ?? '' }}' }">
                @csrf

                <div>
                    <label for="module" class="block text-sm font-medium text-slate-700">Módulo destino *</label>
                    <div class="mt-1 flex gap-4 items-center">
                        <select id="module" name="module" x-model="selectedModule" required
                            class="block w-full max-w-md rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                            @foreach ($modules as $modKey => $modLabel)
                                <option value="{{ $modKey }}" @selected(old('module') === $modKey)>{{ $modLabel }}</option>
                            @endforeach
                        </select>

                        <template x-if="selectedModule">
                            <a :href="'{{ url('imports/template') }}/' + selectedModule"
                               class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                                <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Descargar plantilla oficial
                            </a>
                        </template>
                    </div>
                    @error('module')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="file" class="block text-sm font-medium text-slate-700">Archivo CSV *</label>
                    <div class="mt-1">
                        <input type="file" id="file" name="file" accept=".csv,text/csv,text/plain" required
                            class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Máximo 5 MB y hasta 2,000 filas. Se aceptan delimitadores por coma (,) o punto y coma (;).
                    </p>
                    @error('file')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-md bg-blue-50 p-4 border border-blue-200 text-xs text-blue-800 space-y-1">
                    <p class="font-semibold text-blue-900">Recomendaciones y restricciones:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-blue-700">
                        <li>La primera fila debe contener los encabezados de columna.</li>
                        <li>Los identificadores fiscales (NIT/RUC) y teléfonos conservan sus ceros a la izquierda.</li>
                        <li>No se permite importar contraseñas, roles de sistema, identificadores internos ni cálculos forzados.</li>
                        <li>Las validaciones de negocio y el alcance de permisos (DataScope) se aplican a cada registro importado.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-slate-200">
                    <a href="{{ route('imports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Cancelar
                    </a>
                    <x-button type="submit">
                        Subir y configurar mapeo →
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
