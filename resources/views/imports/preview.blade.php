@extends('layouts.app', ['header' => 'Previsualización de importación', 'subheader' => 'Paso 3: Validación preliminar y confirmación'])

@section('title', 'Previsualización de importación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Importaciones', 'url' => route('imports.index')],
        ['label' => 'Previsualización'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Indicador de pasos --}}
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">✓</span>
                <span class="text-sm font-medium">Archivo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-900 mx-4"></div>
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">✓</span>
                <span class="text-sm font-medium">Mapeo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-900 mx-4"></div>
            <div class="flex items-center space-x-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">3</span>
                <span class="text-sm font-semibold text-slate-900">Previsualización</span>
            </div>
        </div>

        {{-- Resumen de validación --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Módulo</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $moduleLabel }}</p>
                <p class="text-xs text-slate-400 truncate">{{ $originalFilename }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Total filas en archivo</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalRows }}</p>
                <p class="text-xs text-slate-400">Muestra de {{ count($previewRows) }} fila(s)</p>
            </div>
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-green-700">Muestra válidas</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ $validCount }}</p>
                <p class="text-xs text-green-600">Listas para importar</p>
            </div>
            <div class="rounded-lg border border-{{ $errorCount > 0 ? 'red' : 'slate' }}-200 bg-{{ $errorCount > 0 ? 'red-50' : 'slate-50' }} p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-{{ $errorCount > 0 ? 'red' : 'slate' }}-700">Muestra con errores</p>
                <p class="mt-1 text-2xl font-bold text-{{ $errorCount > 0 ? 'red' : 'slate' }}-700">{{ $errorCount }}</p>
                <p class="text-xs text-{{ $errorCount > 0 ? 'red' : 'slate' }}-600">Serán reportadas como incidencias</p>
            </div>
        </div>

        @if ($errorCount > 0)
            <div class="rounded-md bg-amber-50 p-4 border border-amber-200 text-xs text-amber-800">
                <p class="font-semibold text-amber-900">Aviso importante:</p>
                <p>Se detectaron filas con inconsistencias en la muestra. Al confirmar, las filas válidas se insertarán en la base de datos y las filas que fallen serán aisladas en el registro de errores para su descarga posterior.</p>
            </div>
        @endif

        {{-- Tabla de previsualización --}}
        <x-card title="Muestra de datos (hasta 20 filas)" subtitle="Verifica la consistencia de los datos antes de procesar la importación definitiva">
            @if (empty($previewRows))
                <p class="text-sm text-slate-500 py-4 text-center">No se encontraron filas con datos legibles.</p>
            @else
                <div class="overflow-x-auto rounded-md border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Fila</th>
                                <th class="px-3 py-2 text-left">Estado</th>
                                @php
                                    $sampleKeys = array_keys($previewRows[0]['data'] ?? []);
                                @endphp
                                @foreach ($sampleKeys as $k)
                                    <th class="px-3 py-2 text-left">{{ $k }}</th>
                                @endforeach
                                <th class="px-3 py-2 text-left">Observaciones / Errores</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($previewRows as $row)
                                @php $rowValid = (bool) ($row['is_valid'] ?? $row['valid'] ?? true); @endphp
                                <tr class="{{ $rowValid ? 'hover:bg-slate-50' : 'bg-red-50/40 hover:bg-red-50' }}">
                                    <td class="px-3 py-2 font-mono text-slate-500">#{{ $row['row_number'] }}</td>
                                    <td class="px-3 py-2">
                                        @if ($rowValid)
                                            <span class="inline-flex items-center rounded bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-800">
                                                Válida
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-800">
                                                Error
                                            </span>
                                        @endif
                                    </td>
                                    @foreach ($sampleKeys as $k)
                                        <td class="px-3 py-2 text-slate-700 max-w-xs truncate" title="{{ (string) ($row['data'][$k] ?? '') }}">
                                            {{ (string) ($row['data'][$k] ?? '-') }}
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2">
                                        @if (! empty($row['errors']))
                                            <ul class="list-disc list-inside text-red-600 space-y-0.5">
                                                @foreach ($row['errors'] as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-slate-400">Sin errores</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                <a href="{{ route('imports.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    Cancelar importación
                </a>
                <form method="POST" action="{{ route('imports.confirm') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-button type="submit" class="bg-green-600 hover:bg-green-700">
                        Confirmar e importar registros ahora
                    </x-button>
                </form>
            </div>
        </x-card>
    </div>
@endsection
