@extends('layouts.app', ['header' => 'Mapeo de columnas', 'subheader' => 'Paso 2: Relacionar columnas del CSV con los campos del sistema'])

@section('title', 'Mapeo de columnas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Importaciones', 'url' => route('imports.index')],
        ['label' => 'Nueva importación', 'url' => route('imports.create')],
        ['label' => 'Mapeo de columnas'],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-4xl">
        {{-- Indicador de pasos --}}
        <div class="mb-6 flex items-center justify-between border-b border-slate-200 pb-4">
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">✓</span>
                <span class="text-sm font-medium">Archivo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-900 mx-4"></div>
            <div class="flex items-center space-x-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">2</span>
                <span class="text-sm font-semibold text-slate-900">Mapeo</span>
            </div>
            <div class="h-0.5 flex-1 bg-slate-200 mx-4"></div>
            <div class="flex items-center space-x-2 text-slate-400">
                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 text-xs font-semibold">3</span>
                <span class="text-sm font-medium">Previsualización</span>
            </div>
        </div>

        <x-card title="Mapeo para {{ $moduleLabel }}" subtitle="Archivo: {{ $originalFilename }} ({{ $totalRows }} filas detectadas)">
            <form method="POST" action="{{ route('imports.preview') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="module" value="{{ $module }}">
                <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                <input type="hidden" name="original_filename" value="{{ $originalFilename }}">

                <div class="rounded-md bg-slate-50 p-4 border border-slate-200 text-xs text-slate-600">
                    <p>
                        Asocia cada campo de <strong>{{ $moduleLabel }}</strong> con la columna correspondiente de tu archivo CSV.
                        Los campos marcados con <span class="text-red-500 font-bold">*</span> son obligatorios.
                    </p>
                </div>

                <div class="divide-y divide-slate-200 border-t border-b border-slate-200">
                    @foreach ($targetFields as $fieldName => $tf)
                        @php
                            $isReq = $tf['required'] ?? false;
                            // Intentar autoselección por similitud de nombre
                            $autoMatch = null;
                            foreach ($headers as $h) {
                                $hNorm = strtolower(trim(str_replace(['_', '-'], ' ', $h)));
                                $fNorm = strtolower(trim(str_replace(['_', '-'], ' ', $fieldName)));
                                $lNorm = strtolower(trim(str_replace(['_', '-'], ' ', $tf['label'])));
                                if ($hNorm === $fNorm || $hNorm === $lNorm) {
                                    $autoMatch = $h;
                                    break;
                                }
                            }
                        @endphp
                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:items-center">
                            <div class="text-sm">
                                <label for="mapping_{{ $fieldName }}" class="font-medium text-slate-800">
                                    {{ $tf['label'] }}
                                    @if ($isReq)
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <p class="text-xs text-slate-500">{{ $tf['description'] ?? $fieldName }}</p>
                            </div>
                            <div class="mt-1 sm:mt-0 sm:col-span-2">
                                <select id="mapping_{{ $fieldName }}" name="mapping[{{ $fieldName }}]"
                                    class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
                                    <option value="">-- No mapear / Dejar vacío --</option>
                                    @foreach ($headers as $h)
                                        <option value="{{ $h }}" @selected(old("mapping.{$fieldName}", $autoMatch) === $h)>
                                            Columna CSV: "{{ $h }}"
                                        </option>
                                    @endforeach
                                </select>
                                @error("mapping.{$fieldName}")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between pt-4">
                    <a href="{{ route('imports.create') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        ← Volver a cargar archivo
                    </a>
                    <x-button type="submit">
                        Generar previsualización (hasta 20 filas) →
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
