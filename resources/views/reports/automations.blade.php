@extends('layouts.app', ['header' => 'Reporte de automatizaciones', 'subheader' => 'Éxito = success / (success + failed); omitidas excluidas'])

@section('title', 'Reporte de automatizaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Automatizaciones']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.automations')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Activas" :value="\App\Support\ReportFormat::count($summary['active'])"
            href="{{ route('automations.index', ['status' => 'active']) }}" />
        <x-kpi-card label="Ejecuciones" :value="\App\Support\ReportFormat::count($summary['total_runs'])" />
        <x-kpi-card label="Tasa de éxito" :value="\App\Support\ReportFormat::percent($summary['success_ratio'])"
            note="Omitidas no cuentan" />
        <x-kpi-card label="Fallidas" :value="\App\Support\ReportFormat::count($summary['by_status']['failed'] ?? 0)" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por estado" subtitle="Ejecuciones en el rango"
            :rows="collect($summary['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-card title="Top automatizaciones" subtitle="Solo visibles en tu alcance">
            @if (empty($summary['top']))
                <p class="text-sm text-slate-500">Sin ejecuciones en el período.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($summary['top'] as $row)
                        <li class="flex items-center gap-2 py-2">
                            <a href="{{ route('automations.show', $row['automation_id']) }}" class="font-medium hover:underline">{{ $row['name'] }}</a>
                            <span class="ml-auto text-xs text-slate-400">{{ $row['runs'] }} runs · {{ $row['failures'] }} fallos</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
