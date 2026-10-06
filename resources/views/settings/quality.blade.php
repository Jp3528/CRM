@extends('layouts.app', ['header' => 'Calidad y diagnóstico de datos', 'subheader' => 'Monitoreo de integridad, campos obligatorios incompletos y candidatos a duplicados'])

@section('title', 'Calidad y diagnóstico de datos')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Calidad de datos'],
    ]" />
@endsection

@section('content')
    <div class="space-y-6">
        <div class="rounded-lg bg-blue-50 p-4 border border-blue-200 text-xs text-blue-800">
            <p class="font-semibold">Diagnósticos basados estrictamente en el alcance de datos (DataScope):</p>
            <p class="mt-0.5">Las métricas reflejan únicamente registros visibles según tu rol y equipo. Las sugerencias permiten acceder al listado filtrado para que el responsable comercial sanee la información directamente.</p>
        </div>

        {{-- Tarjetas de indicadores de calidad --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($metrics as $metric)
                <div class="rounded-lg bg-white p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $metric['title'] }}</span>
                            <span class="rounded px-2 py-0.5 text-xs font-bold font-mono {{ $metric['count'] > 0 ? ($metric['status'] === 'danger' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $metric['count'] }}
                            </span>
                        </div>
                        <p class="mt-2 text-2xl font-bold font-mono text-slate-900">{{ $metric['count'] }}</p>
                        <p class="mt-1 text-xs text-slate-500 leading-relaxed">{{ $metric['description'] }}</p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <a href="{{ $metric['route'] }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center justify-between">
                            <span>Ver registros afectados</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Candidatos a duplicados detectados --}}
        <x-card title="Candidatos a duplicados por correo electrónico" subtitle="Contactos que comparten la misma dirección de email dentro de tu alcance">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700">
                        <tr>
                            <th class="px-6 py-3">Correo electrónico</th>
                            <th class="px-6 py-3 text-center">Registros detectados</th>
                            <th class="px-6 py-3 text-right">Acción recomendada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($duplicateEmails as $dup)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-mono text-xs text-slate-900 font-semibold">
                                    {{ $dup->email }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs font-bold text-amber-700">
                                    {{ $dup->count }} contactos
                                </td>
                                <td class="px-6 py-4 text-right text-xs">
                                    <a href="{{ route('contacts.index', ['search' => $dup->email]) }}" class="font-semibold text-blue-600 hover:text-blue-800">
                                        Filtrar contactos →
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-slate-400">
                                    No se detectaron duplicados de correo en los contactos visibles.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
