@extends('layouts.app', ['header' => 'Detalle de importación #' . $import->id, 'subheader' => 'Resultados y registro de incidencias del proceso'])

@section('title', 'Detalle de importación #' . $import->id)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Importaciones', 'url' => route('imports.index')],
        ['label' => '#' . $import->id],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        {{-- Métricas del proceso --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Módulo</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ App\Support\ImportCatalog::label($import->module) }}</p>
                <p class="text-xs text-slate-400 truncate">{{ $import->original_filename }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total procesadas</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $import->total_rows }}</p>
                <p class="text-xs text-slate-500">Filas en archivo</p>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-green-700">Filas importadas</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ $import->successful_rows }}</p>
                <p class="text-xs text-green-600">Registros creados</p>
            </div>
            <div class="rounded-lg border border-{{ $import->failed_rows > 0 ? 'red' : 'slate' }}-200 bg-{{ $import->failed_rows > 0 ? 'red-50' : 'slate-50' }} p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-{{ $import->failed_rows > 0 ? 'red' : 'slate' }}-700">Filas fallidas</p>
                <p class="mt-1 text-2xl font-bold text-{{ $import->failed_rows > 0 ? 'red' : 'slate' }}-700">{{ $import->failed_rows }}</p>
                <p class="text-xs text-{{ $import->failed_rows > 0 ? 'red' : 'slate' }}-600">Omitidas por validación</p>
            </div>
        </div>

        {{-- Información técnica y auditoría --}}
        <x-card title="Información del proceso">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="font-medium text-slate-500">Estado</dt>
                    <dd class="mt-1"><x-status-badge :status="$import->status" /></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Ejecutado por</dt>
                    <dd class="mt-1 text-slate-900">{{ $import->creator?->name ?? 'Sistema' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Fecha de ejecución</dt>
                    <dd class="mt-1 text-slate-900">{{ $import->created_at->format('d/m/Y H:i:s') }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                <a href="{{ route('imports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    ← Volver a importaciones
                </a>
                <div class="flex items-center space-x-3">
                    @if (Route::has($import->module . '.index'))
                        <a href="{{ route($import->module . '.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                            Ver {{ strtolower(App\Support\ImportCatalog::label($import->module)) }} creadas →
                        </a>
                    @endif
                    @if ($import->failed_rows > 0)
                        <a href="{{ route('imports.errors', $import) }}" class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-red-700">
                            <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar reporte CSV de errores
                        </a>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Tabla de incidencias si existen --}}
        @if ($import->errors->isNotEmpty())
            <x-card title="Detalle de incidencias ({{ $import->errors->count() }})" subtitle="Listado de filas rechazadas durante el procesamiento">
                <div class="overflow-x-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Fila #</th>
                                <th class="px-3 py-2 text-left">Campo</th>
                                <th class="px-3 py-2 text-left">Motivo del rechazo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($import->errors as $err)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-mono font-medium text-slate-900">#{{ $err->row_number }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $err->field ?? 'general' }}</td>
                                    <td class="px-3 py-2 text-red-600 font-medium">{{ $err->message }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @else
            <div class="rounded-md bg-green-50 p-6 border border-green-200 text-center">
                <p class="text-sm font-semibold text-green-800">¡Importación completada sin errores!</p>
                <p class="text-xs text-green-700 mt-1">Todas las filas del archivo CSV se procesaron y guardaron satisfactoriamente en la base de datos.</p>
            </div>
        @endif
    </div>
@endsection
