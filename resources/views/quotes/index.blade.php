@extends('layouts.app', ['header' => 'Cotizaciones', 'subheader' => 'Documentos comerciales'])

@section('title', 'Cotizaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Cotizaciones']]" />
@endsection

@section('content')
    <x-card title="Cotizaciones" subtitle="{{ $quotes->total() }} registro(s)">
        <form method="GET" action="{{ route('quotes.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar número, empresa, contacto, oportunidad…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-3 lg:col-span-3">
            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="company_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $filters['company_id'] === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
            </select>
            <select name="owner_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <div class="sm:col-span-2 md:col-span-3 lg:col-span-3 grid grid-cols-2 gap-2">
                <input type="date" name="issued_from" value="{{ $filters['issued_from'] }}" title="Emitida desde" aria-label="Emitida desde"
                    class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                <input type="date" name="issued_to" value="{{ $filters['issued_to'] }}" title="Emitida hasta" aria-label="Emitida hasta"
                    class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
            </div>
            <div class="col-span-full flex flex-wrap items-center gap-2 pt-1">
                <x-button>Buscar</x-button>
                <a href="{{ route('quotes.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'quotes'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Quote::class)
                    <a href="{{ route('quotes.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva cotización</a>
                @endcan
            </div>
        </form>

        @if ($quotes->isEmpty())
            <x-empty-state title="No hay cotizaciones." message="Crea la primera cotización comercial."
                :action-url="auth()->user()->can('create', App\Models\Quote::class) ? route('quotes.create') : null" action-label="Nueva cotización" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('quotes.index', array_merge(request()->query(), ['sort' => 'number', 'direction' => $filters['sort'] === 'number' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Número{{ $filters['sort'] === 'number' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Empresa / Contacto</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('quotes.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('quotes.index', array_merge(request()->query(), ['sort' => 'total', 'direction' => $filters['sort'] === 'total' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Total{{ $filters['sort'] === 'total' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('quotes.index', array_merge(request()->query(), ['sort' => 'issue_date', 'direction' => $filters['sort'] === 'issue_date' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Emisión{{ $filters['sort'] === 'issue_date' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($quotes as $quote)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono font-medium">
                                    <a href="{{ route('quotes.show', $quote) }}" class="hover:underline">{{ $quote->number }}</a>
                                    @if ($quote->is_expired)<span class="ml-1"><x-badge color="yellow">Vencida</x-badge></span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $quote->company?->trade_name ?? '—' }}
                                    @if ($quote->contact)<span class="block text-xs">{{ $quote->contact->first_name }} {{ $quote->contact->last_name }}</span>@endif
                                    @if ($quote->opportunity)<span class="block text-xs text-slate-400">{{ $quote->opportunity->name }}</span>@endif
                                </td>
                                <td class="px-4 py-2"><x-status-badge :status="$quote->status" /></td>
                                <td class="px-4 py-2 font-medium">{{ number_format($quote->total, 2) }} {{ $quote->currency }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $quote->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $quote->issue_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('quotes.show', $quote) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $quote) @if (in_array($quote->status, \App\Models\Quote::EDITABLE_STATUSES, true)) · <a href="{{ route('quotes.edit', $quote) }}" class="text-slate-600 hover:underline">Editar</a>@endif @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $quotes->links() }}</div>
        @endif
    </x-card>
@endsection
