@extends('layouts.app', ['header' => 'Búsqueda Global', 'subheader' => 'Resultados en entidades autorizadas'])

@section('title', 'Búsqueda Global')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Búsqueda']]" />
@endsection

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    {{-- Formulario de búsqueda --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <form method="GET" action="{{ route('search.index') }}" class="flex items-center gap-3">
            <div class="relative flex-1">
                <input type="text" name="q" value="{{ $query }}" placeholder="Buscar por nombre, RUC, email, código de ticket, etc. (mínimo 2 caracteres)..."
                       class="w-full rounded-lg border border-slate-300 py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:ring-cyan-500">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    🔍
                </span>
            </div>
            <button type="submit" class="inline-flex items-center rounded-lg bg-cyan-700 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-cyan-800 transition-colors">
                Buscar
            </button>
        </form>
    </div>

    @if (mb_strlen($query) < 2 && $query !== '')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Ingresa al menos 2 caracteres para realizar una búsqueda precisa.
        </div>
    @elseif ($query !== '')
        <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
            <span>Resultados para: <strong class="text-slate-900 font-semibold font-mono">"{{ $query }}"</strong></span>
            <span>Total encontrados: <strong>{{ $totalCount }}</strong></span>
        </div>

        @if ($totalCount === 0)
            <div class="rounded-xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <p class="text-base font-medium text-slate-700">No se encontraron resultados coincidentes</p>
                <p class="text-xs text-slate-400 mt-1">Verifica la ortografía o intenta con términos más generales dentro de los módulos a los que tienes acceso.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Empresas --}}
                @if ($results['companies']->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Empresas</h3>
                            <span class="text-xs font-semibold text-slate-400">{{ $results['companies']->count() }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($results['companies'] as $comp)
                                <li class="py-2.5 flex items-center justify-between">
                                    <a href="{{ route('companies.show', $comp) }}" class="font-medium text-cyan-800 hover:text-cyan-900 hover:underline">
                                        {{ $comp->trade_name }}
                                    </a>
                                    <span class="text-xs font-mono text-slate-500">{{ $comp->tax_id ?? 'Sin documento' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Contactos --}}
                @if ($results['contacts']->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Contactos</h3>
                            <span class="text-xs font-semibold text-slate-400">{{ $results['contacts']->count() }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($results['contacts'] as $cont)
                                <li class="py-2.5 flex items-center justify-between">
                                    <a href="{{ route('contacts.show', $cont) }}" class="font-medium text-cyan-800 hover:text-cyan-900 hover:underline">
                                        {{ $cont->first_name }} {{ $cont->last_name }}
                                    </a>
                                    <span class="text-xs text-slate-500">{{ $cont->company?->trade_name ?? $cont->email }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Leads --}}
                @if ($results['leads']->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Leads</h3>
                            <span class="text-xs font-semibold text-slate-400">{{ $results['leads']->count() }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($results['leads'] as $lead)
                                <li class="py-2.5 flex items-center justify-between">
                                    <a href="{{ route('leads.show', $lead) }}" class="font-medium text-cyan-800 hover:text-cyan-900 hover:underline">
                                        {{ $lead->first_name }} {{ $lead->last_name }}
                                    </a>
                                    <span class="text-xs text-slate-500">{{ $lead->company_name ?? ucfirst($lead->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Oportunidades --}}
                @if ($results['opportunities']->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Oportunidades</h3>
                            <span class="text-xs font-semibold text-slate-400">{{ $results['opportunities']->count() }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($results['opportunities'] as $opp)
                                <li class="py-2.5 flex items-center justify-between">
                                    <a href="{{ route('opportunities.show', $opp) }}" class="font-medium text-cyan-800 hover:text-cyan-900 hover:underline">
                                        {{ $opp->name }}
                                    </a>
                                    <span class="text-xs font-mono text-slate-600">{{ $opp->amount !== null ? number_format((float) $opp->amount, 2).' '.$opp->currency : '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Tickets --}}
                @if ($results['tickets']->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Tickets de Soporte</h3>
                            <span class="text-xs font-semibold text-slate-400">{{ $results['tickets']->count() }}</span>
                        </div>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($results['tickets'] as $tkt)
                                <li class="py-2.5 flex items-center justify-between">
                                    <a href="{{ route('tickets.show', $tkt) }}" class="font-medium text-cyan-800 hover:text-cyan-900 hover:underline truncate max-w-[240px]">
                                        <span class="font-mono text-xs">{{ $tkt->number }}:</span> {{ $tkt->subject }}
                                    </a>
                                    <span class="text-xs font-medium text-slate-500">{{ ucfirst($tkt->status) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
