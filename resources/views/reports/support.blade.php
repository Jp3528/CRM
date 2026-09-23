@extends('layouts.app', ['header' => 'Reporte de soporte', 'subheader' => 'Estados = snapshot actual; sin SLA contractual'])

@section('title', 'Reporte de soporte')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Soporte']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.support')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Creados" :value="\App\Support\ReportFormat::count($summary['created'])"
            href="{{ route('tickets.index') }}" />
        <x-kpi-card label="Resueltos" :value="\App\Support\ReportFormat::count($summary['resolved'])" />
        <x-kpi-card label="Abiertos (actual)" :value="\App\Support\ReportFormat::count($summary['open_snapshot'])"
            note="Snapshot, no histórico" />
        <x-kpi-card label="Urgentes (actual)" :value="\App\Support\ReportFormat::count($summary['urgent_snapshot'])" />
        <x-kpi-card label="Pendientes (actual)" :value="\App\Support\ReportFormat::count($summary['pending_snapshot'])" />
        <x-kpi-card label="Sin asignar (actual)" :value="\App\Support\ReportFormat::count($summary['unassigned_snapshot'])" />
        <x-kpi-card label="Primera respuesta media" :value="\App\Support\ReportFormat::duration($summary['avg_first_response_seconds'])" />
        <x-kpi-card label="Resolución media" :value="\App\Support\ReportFormat::duration($summary['avg_resolution_seconds'])" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por estado" subtitle="Creados en el rango"
            :rows="collect($breakdowns['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-bar-chart title="Por prioridad" subtitle="Creados en el rango"
            :rows="collect($breakdowns['by_priority'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-bar-chart title="Por categoría" subtitle="Creados en el rango"
            :rows="collect($breakdowns['by_category'])->map(fn ($v, $k) => ['label' => $k, 'value' => $v, 'display' => $v])->all()" />
        <x-bar-chart title="Por asignado" subtitle="Creados en el rango"
            :rows="collect($breakdowns['by_assignee'])->map(fn ($v, $k) => ['label' => $k, 'value' => $v, 'display' => $v])->all()" />
    </div>
@endsection
