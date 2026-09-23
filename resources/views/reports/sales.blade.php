@extends('layouts.app', ['header' => 'Reporte de ventas', 'subheader' => 'Confirmadas y completadas · por moneda'])

@section('title', 'Reporte de ventas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Ventas']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.sales')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Operaciones" :value="\App\Support\ReportFormat::count($summary['deals'])"
            href="{{ route('sales.index', ['status' => 'confirmed']) }}" note="Rango seleccionado" />
        @foreach ($summary['by_currency'] as $currency => $row)
            <x-kpi-card :label="'Total ' . $currency" :value="\App\Support\ReportFormat::money($row['total'], $currency)"
                :secondary="'Promedio ' . \App\Support\ReportFormat::money($row['average'], $currency)"
                :trend="$row['trend']" :href="route('sales.index', ['status' => 'confirmed'])" />
        @endforeach
        @if (empty($summary['by_currency']))
            <x-kpi-card label="Total" value="—" note="Sin datos en el período" />
        @endif
    </div>

    @if ($summary['cancelled']->isNotEmpty())
        <x-card title="Canceladas (separado)" subtitle="No suman al total">
            <ul class="space-y-1 text-sm">
                @foreach ($summary['cancelled'] as $row)
                    <li>{{ $row->deals }} × {{ \App\Support\ReportFormat::money($row->total, $row->currency) }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Tendencia de ventas" subtitle="Suma por período (por moneda en etiqueta; barra = mayor moneda)"
            :rows="collect($trend)->map(fn ($b) => ['label' => $b['label'], 'value' => collect($b['totals'])->map(fn ($v) => (float) $v)->max() ?? 0, 'display' => collect($b['totals'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') ?: '—'])->all()" />
        <x-bar-chart title="Por responsable (top 10)" subtitle="Solo usuarios en tu alcance"
            :rows="collect($byOwner)->map(fn ($o) => ['label' => $o['name'], 'value' => $o['deals'], 'display' => $o['deals'] . ' op.'])->all()" />
    </div>

    <x-card title="Por empresa (top 10)" subtitle="Solo empresas visibles; montos por moneda">
        @if (empty($byCompany))
            <p class="text-sm text-slate-500">No hay datos para el período seleccionado.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-2 text-left">Empresa</th><th class="px-4 py-2 text-right">Operaciones</th><th class="px-4 py-2 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($byCompany as $c)
                            <tr>
                                <td class="px-4 py-2">{{ $c['name'] ?? 'Empresa no visible' }}</td>
                                <td class="px-4 py-2 text-right">{{ $c['deals'] }}</td>
                                <td class="px-4 py-2 text-right">{{ collect($c['totals'])->map(fn ($v, $cur) => \App\Support\ReportFormat::money($v, $cur))->implode(' · ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
