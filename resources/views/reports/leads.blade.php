@extends('layouts.app', ['header' => 'Reporte de leads', 'subheader' => 'Conversión = convertidos / creados en el rango'])

@section('title', 'Reporte de leads')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Leads']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.leads')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Creados" :value="\App\Support\ReportFormat::count($summary['created'])"
            href="{{ route('leads.index') }}" />
        <x-kpi-card label="Convertidos" :value="\App\Support\ReportFormat::count($summary['converted'])" />
        <x-kpi-card label="Tasa de conversión" :value="\App\Support\ReportFormat::percent($summary['conversion_rate'])"
            note="Convertidos / creados en el rango" />
        <x-kpi-card label="Calificados" :value="\App\Support\ReportFormat::count($summary['by_status']['qualified'] ?? 0)"
            note="Estado actual de los creados" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por origen" subtitle="Creados en el rango"
            :rows="collect($bySource)->map(fn ($s) => ['label' => ucfirst($s['source']), 'value' => $s['total'], 'display' => $s['total']])->all()" />
        <x-bar-chart title="Por responsable (top 10)" subtitle="Solo usuarios en tu alcance"
            :rows="collect($byOwner)->map(fn ($o) => ['label' => $o['name'], 'value' => $o['total'], 'display' => $o['total']])->all()" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Distribución de score" subtitle="Creados en el rango con score"
            :rows="collect($scores)->map(fn ($v, $k) => ['label' => $k, 'value' => $v, 'display' => $v])->all()" />
        <x-bar-chart title="Por estado" subtitle="Estado actual de los creados en el rango"
            :rows="collect($summary['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
    </div>
@endsection
