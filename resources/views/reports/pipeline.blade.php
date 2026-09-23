@extends('layouts.app', ['header' => 'Reporte de pipeline', 'subheader' => 'Oportunidades abiertas · por moneda'])

@section('title', 'Reporte de pipeline')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes', 'url' => route('reports.index')], ['label' => 'Pipeline']]" />
@endsection

@section('content')
    <x-report-filters :action="route('reports.pipeline')" :filters="$filters" :owners="$owners" />

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi-card label="Abiertas" :value="\App\Support\ReportFormat::count($summary['deals'])"
            href="{{ route('opportunities.index', ['status' => 'open']) }}" />
        @foreach ($summary['by_currency'] as $currency => $row)
            <x-kpi-card :label="'Pipeline ' . $currency" :value="\App\Support\ReportFormat::money($row['amount'], $currency)"
                :secondary="'Ponderado ' . \App\Support\ReportFormat::money($row['weighted'], $currency) . ' · Prom. ' . \App\Support\ReportFormat::money($row['average'], $currency)" />
        @endforeach
        @if (empty($summary['by_currency']))
            <x-kpi-card label="Pipeline" value="—" note="Sin abiertas" />
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-bar-chart title="Por etapa" subtitle="Conteo de abiertas por etapa real"
            :rows="collect($byStage)->map(fn ($s) => ['label' => $s['pipeline'] . ' / ' . $s['stage'], 'value' => $s['deals'], 'display' => $s['deals'] . ' · ' . collect($s['amounts'])->map(fn ($v, $c) => \App\Support\ReportFormat::money($v, $c))->implode(' · ')])->all()" />
        <x-card title="Cierre esperado" subtitle="Solo abiertas">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                @foreach (['overdue' => 'Vencidas', 'this_month' => 'Este mes', 'next_month' => 'Próximo mes', 'later' => 'Más adelante', 'no_date' => 'Sin fecha'] as $k => $label)
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">{{ $label }}</dt>
                        <dd class="text-lg font-semibold">{{ $buckets[$k]['deals'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>
    </div>

    <x-card title="Sin actualización > {{ $staleDays }} días ({{ $stale['total'] }})" subtitle="Hecho observable, sin juicio de riesgo">
        <form method="GET" action="{{ route('reports.pipeline') }}" class="mb-3 flex items-center gap-2 text-sm">
            @foreach (request()->except('stale_days') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="stale_days" class="rounded-md border-slate-300 px-2 py-1.5 text-sm" onchange="this.form.submit()">
                @foreach ([7, 14, 30, 60, 90] as $d)<option value="{{ $d }}" @selected($staleDays === $d)>{{ $d }} días</option>@endforeach
            </select>
        </form>
        @if ($stale['items']->isEmpty())
            <p class="text-sm text-slate-500">Sin oportunidades estancadas.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($stale['items'] as $opp)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('opportunities.show', $opp) }}" class="font-medium hover:underline">{{ $opp->name }}</a>
                        <span class="text-xs text-slate-400">{{ $opp->company?->trade_name ?? '—' }} · {{ $opp->owner?->name ?? 'Sin responsable' }}</span>
                        <span class="ml-auto text-xs text-slate-400">{{ $opp->updated_at->format('Y-m-d') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
