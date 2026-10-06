@extends('layouts.app', ['header' => 'Ventas', 'subheader' => 'Documentos de venta'])

@section('title', 'Ventas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ventas']]" />
@endsection

@section('content')
    <x-card title="Ventas" subtitle="{{ $sales->total() }} registro(s)">
        <form method="GET" action="{{ route('sales.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar número, cotización, empresa…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="company_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $filters['company_id'] === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
            </select>
            <select name="owner_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <input type="date" name="sold_from" value="{{ $filters['sold_from'] }}" title="Desde"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
                <input type="date" name="sold_to" value="{{ $filters['sold_to'] }}" title="Hasta"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
            </div>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'sales'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Sale::class)
                    <a href="{{ route('sales.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva venta</a>
                @endcan
            </div>
        </form>

        @if ($sales->isEmpty())
            <x-empty-state title="No hay ventas." message="Convierte una cotización aceptada o crea una venta manual."
                :action-url="auth()->user()->can('create', App\Models\Sale::class) ? route('sales.create') : null" action-label="Nueva venta" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('sales.index', array_merge(request()->query(), ['sort' => 'number', 'direction' => $filters['sort'] === 'number' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Número{{ $filters['sort'] === 'number' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Empresa / Cotización</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('sales.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('sales.index', array_merge(request()->query(), ['sort' => 'total', 'direction' => $filters['sort'] === 'total' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Total{{ $filters['sort'] === 'total' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('sales.index', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $filters['sort'] === 'sale_date' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Fecha{{ $filters['sort'] === 'sale_date' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($sales as $sale)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono font-medium">
                                    <a href="{{ route('sales.show', $sale) }}" class="hover:underline">{{ $sale->number }}</a>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $sale->company?->trade_name ?? '—' }}
                                    @if ($sale->quote)<span class="block text-xs text-slate-400">{{ $sale->quote->number }}</span>@endif
                                </td>
                                <td class="px-4 py-2"><x-status-badge :status="$sale->status" /></td>
                                <td class="px-4 py-2 font-medium">{{ number_format($sale->total, 2) }} {{ $sale->currency }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $sale->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $sale->sale_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $sale) @if ($sale->status === 'draft') · <a href="{{ route('sales.edit', $sale) }}" class="text-slate-600 hover:underline">Editar</a>@endif @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $sales->links() }}</div>
        @endif
    </x-card>
@endsection
