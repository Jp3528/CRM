@extends('layouts.app', ['header' => 'Reporte de campañas', 'subheader' => 'Sin open rate/CTR/delivery: no hay proveedores reales'])

@section('title', 'Reporte de campañas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Campañas']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.campaigns')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Campañas" :value="\App\Support\ReportFormat::count($summary['total'])"
            href="{{ route('campaigns.index') }}" />
        <x-kpi-card label="Miembros visibles" :value="\App\Support\ReportFormat::count($summary['visible_members'])"
            note="Solo objetivos en tu alcance" />
        <x-kpi-card label="Comunicaciones simuladas" :value="\App\Support\ReportFormat::count($summary['simulated_in_range'])"
            note="Registradas en el rango" />
        <x-kpi-card label="Uso de presupuesto" :value="\App\Support\ReportFormat::percent($summary['budget_utilization'])"
            note="Costo real / presupuesto" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por estado" subtitle="Campañas en tu alcance"
            :rows="collect($summary['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-bar-chart title="Miembros por estado" subtitle="Solo visibles en tu alcance"
            :rows="collect($summary['members_by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-card title="Finanzas de referencia" subtitle="Montos sin moneda; expectativa manual, no revenue generado">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Presupuesto</dt><dd class="font-medium">{{ \App\Support\ReportFormat::money($summary['budget']) }}</dd></div>
                <div><dt class="text-slate-500">Ingreso esperado</dt><dd class="font-medium">{{ \App\Support\ReportFormat::money($summary['expected_revenue']) }}</dd></div>
                <div><dt class="text-slate-500">Costo real</dt><dd class="font-medium">{{ \App\Support\ReportFormat::money($summary['actual_cost']) }}</dd></div>
            </dl>
        </x-card>
    </div>
@endsection
