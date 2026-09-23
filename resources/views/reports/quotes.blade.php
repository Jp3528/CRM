@extends('layouts.app', ['header' => 'Reporte de cotizaciones', 'subheader' => 'Aceptación = aceptadas / (aceptadas + rechazadas)'])

@section('title', 'Reporte de cotizaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Cotizaciones']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.quotes')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Creadas" :value="\App\Support\ReportFormat::count($summary['created'])"
            href="{{ route('quotes.index') }}" />
        <x-kpi-card label="Enviadas" :value="\App\Support\ReportFormat::count($summary['by_status']['sent'] ?? 0)" />
        <x-kpi-card label="Aceptadas" :value="\App\Support\ReportFormat::count($summary['by_status']['accepted'] ?? 0)"
            href="{{ route('quotes.index', ['status' => 'accepted']) }}" />
        <x-kpi-card label="Tasa de aceptación" :value="\App\Support\ReportFormat::percent($summary['acceptance_rate'])"
            note="Sin respuesta no cuenta como decisión" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por estado" subtitle="Creadas en el rango por issue_date"
            :rows="collect($summary['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-card title="Valores por moneda" subtitle="Vencida = borrador/enviada con vigencia pasada (calculada)">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Cotizado total</dt><dd class="font-medium">{{ collect($summary['quoted_by_currency'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Valor aceptado</dt><dd class="font-medium">{{ collect($summary['accepted_by_currency'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Vencidas (calculadas)</dt><dd class="font-medium">{{ $summary['expired_computed'] }}</dd></div>
            </dl>
        </x-card>
    </div>
@endsection
