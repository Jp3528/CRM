@extends('layouts.app', ['header' => 'Reportes', 'subheader' => 'Centro de análisis por alcance'])

@section('title', 'Reportes')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reportes']]" />
@endsection

@section('content')
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            <x-card :title="$card['label']" :subtitle="$card['desc']">
                <a href="{{ route($card['route']) }}" class="text-sm font-medium text-slate-700 hover:underline">Abrir reporte →</a>
            </x-card>
        @endforeach
        @if ($canForecast)
            <x-card title="Forecast" subtitle="Pipeline ponderado por mes de cierre esperado.">
                <a href="{{ route('forecast.index') }}" class="text-sm font-medium text-slate-700 hover:underline">Abrir forecast →</a>
            </x-card>
        @endif
    </div>
    @if (empty($cards) && ! $canForecast)
        <x-card title="Sin reportes disponibles" subtitle="Tu rol no incluye módulos con métricas.">
            <p class="text-sm text-slate-500">Solicita acceso a los módulos correspondientes.</p>
        </x-card>
    @endif
@endsection
