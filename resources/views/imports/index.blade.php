@extends('layouts.app', ['header' => 'Importaciones', 'subheader' => 'Historial y asistente de importación de datos CSV'])

@section('title', 'Importaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Importaciones']]" />
@endsection

@section('content')
    <x-card title="Historial de importaciones" subtitle="{{ $imports->total() }} proceso(s) registrado(s)">
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-slate-600">
                Carga masiva de Empresas, Contactos y Leads mediante archivos CSV estandarizados.
            </p>
            @can('create', App\Models\DataImport::class)
                <a href="{{ route('imports.create') }}" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Nueva importación
                </a>
            @endcan
        </div>

        @if ($imports->isEmpty())
            <x-empty-state
                title="No hay importaciones registradas"
                message="Inicia una nueva importación para cargar registros masivos de forma segura."
                :action-url="auth()->user()->can('create', App\Models\DataImport::class) ? route('imports.create') : null"
                action-label="Nueva importación"
            />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Módulo</th>
                            <th class="px-4 py-3 text-left">Archivo original</th>
                            <th class="px-4 py-3 text-center">Total</th>
                            <th class="px-4 py-3 text-center">Exitosas</th>
                            <th class="px-4 py-3 text-center">Fallidas</th>
                            <th class="px-4 py-3 text-left">Estado</th>
                            <th class="px-4 py-3 text-left">Usuario</th>
                            <th class="px-4 py-3 text-left">Fecha</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($imports as $imp)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">#{{ $imp->id }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">
                                        {{ App\Support\ImportCatalog::label($imp->module) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-xs truncate" title="{{ $imp->original_filename }}">
                                    {{ $imp->original_filename }}
                                </td>
                                <td class="px-4 py-3 text-center font-medium text-slate-800">{{ $imp->total_rows }}</td>
                                <td class="px-4 py-3 text-center font-medium text-green-600">{{ $imp->successful_rows }}</td>
                                <td class="px-4 py-3 text-center font-medium {{ $imp->failed_rows > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                    {{ $imp->failed_rows }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$imp->status" />
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $imp->creator?->name ?? 'Sistema' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $imp->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <a href="{{ route('imports.show', $imp) }}" class="text-xs font-medium text-slate-700 hover:text-slate-900 underline">
                                        Detalle
                                    </a>
                                    @if ($imp->failed_rows > 0)
                                        <a href="{{ route('imports.errors', $imp) }}" class="text-xs font-medium text-red-600 hover:text-red-800 underline">
                                            Descargar errores
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $imports->links() }}
            </div>
        @endif
    </x-card>
@endsection
