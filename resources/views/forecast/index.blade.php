@extends('layouts.app', ['header' => 'Forecast comercial', 'subheader' => 'Ponderado operacional · NO es IA ni predicción estadística'])

@section('title', 'Forecast')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Forecast']]" />
@endsection

@section('content')
    <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-600">
        Ponderado = monto × probabilidad / 100, por moneda y sin conversión. Solo oportunidades abiertas con fecha estimada en el rango. Ganadas reales van aparte.
    </div>

    <x-report-filters :action="route('forecast.index')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($forecast['totals'] as $row)
            <x-kpi-card :label="'Forecast ' . $row['currency']" :value="\App\Support\ReportFormat::money($row['weighted'], $row['currency'])"
                :secondary="$row['deals'] . ' op. · nominal ' . \App\Support\ReportFormat::money($row['amount'], $row['currency']) . ' · prob. prom. ' . ($row['avg_probability'] ?? '—') . '%'" />
        @endforeach
        @if (empty($forecast['totals']))
            <x-kpi-card label="Forecast" value="—" note="Sin abiertas con fecha en el rango" />
        @endif
        <x-kpi-card label="Sin fecha estimada" :value="\App\Support\ReportFormat::count($forecast['missing_close_date']['deals'])"
            note="Dato de calidad, fuera del forecast" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por mes de cierre" subtitle="Nominal por moneda en etiqueta"
            :rows="collect($forecast['months'])->map(fn ($m) => ['label' => $m['label'], 'value' => $m['deals'], 'display' => $m['deals'] . ' op. · ' . collect($m['weighted'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ')])->all()" />
        <x-bar-chart title="Por etapa" subtitle="Solo abiertas en rango"
            :rows="collect($forecast['by_stage'])->map(fn ($s) => ['label' => $s['stage'], 'value' => $s['deals'], 'display' => $s['deals'] . ' op.'])->all()" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Por responsable (top 10)" subtitle="Solo usuarios en tu alcance">
            @if (empty($forecast['by_owner']))
                <p class="text-sm text-slate-500">Sin datos.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($forecast['by_owner'] as $o)
                        <li class="py-2">
                            <p class="font-medium">{{ $o['name'] }} <span class="font-normal text-slate-400">· {{ $o['deals'] }} op.</span></p>
                            <p class="text-xs text-slate-500">{{ collect($o['weighted'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card title="Ganado real en el rango" subtitle="Serie separada por actual_close_date">
            @if (empty($forecast['won_actual']))
                <p class="text-sm text-slate-500">Sin ganadas con cierre en el rango.</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($forecast['won_actual'] as $row)
                        <li>{{ $row['deals'] }} × {{ \App\Support\ReportFormat::money($row['amount'], $row['currency']) }}</li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
