@extends('layouts.app', ['header' => 'Facturación interna', 'subheader' => 'Documentos internos; no fiscales'])

@section('title', 'Reporte de facturación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Facturación interna']]" />
@endsection

@section('content')
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
        Documentos internos de gestión; no constituyen factura tributaria/fiscal. Sin pagos parciales: pagada es marca, no monto.
    </div>

    <x-report-filters :action="route('reports.invoices')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Emitidas" :value="\App\Support\ReportFormat::count($summary['issued'])"
            href="{{ route('invoices.index') }}" />
        <x-kpi-card label="Pagadas" :value="\App\Support\ReportFormat::count($summary['by_status']['paid'] ?? 0)"
            href="{{ route('invoices.index', ['status' => 'paid']) }}" />
        <x-kpi-card label="Vencidas" :value="\App\Support\ReportFormat::count($summary['overdue'])"
            note="Vencida y pendiente de pago" />
        <x-kpi-card label="Canceladas" :value="\App\Support\ReportFormat::count($summary['by_status']['cancelled'] ?? 0)" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por estado" subtitle="Emitidas en el rango por issue_date"
            :rows="collect($summary['by_status'])->map(fn ($v, $k) => ['label' => ucfirst($k), 'value' => $v, 'display' => $v])->all()" />
        <x-card title="Montos por moneda" subtitle="Pendiente = no pagada ni cancelada">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">Facturado</dt><dd class="font-medium">{{ collect($summary['invoiced_by_currency'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Pagado</dt><dd class="font-medium">{{ collect($summary['paid_by_currency'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Pendiente</dt><dd class="font-medium">{{ collect($summary['outstanding_by_currency'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—' }}</dd></div>
            </dl>
        </x-card>
    </div>
@endsection
