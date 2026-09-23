@extends('layouts.app', ['header' => 'Facturas', 'subheader' => 'Documentos internos, no fiscales'])

@section('title', 'Facturas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Facturas']]" />
@endsection

@section('content')
    <x-card title="Facturas internas" subtitle="{{ $invoices->total() }} registro(s) · Documentos administrativos, sin validez fiscal">
        <form method="GET" action="{{ route('invoices.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar número, venta, empresa…"
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
                <input type="date" name="issued_from" value="{{ $filters['issued_from'] }}" title="Emitida desde"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
                <input type="date" name="issued_to" value="{{ $filters['issued_to'] }}" title="Emitida hasta"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
            </div>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
            </div>
        </form>

        @if ($invoices->isEmpty())
            <x-empty-state title="No hay facturas." message="Genera una factura desde una venta confirmada o completada." />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('invoices.index', array_merge(request()->query(), ['sort' => 'number', 'direction' => $filters['sort'] === 'number' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Número{{ $filters['sort'] === 'number' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Venta / Empresa</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('invoices.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('invoices.index', array_merge(request()->query(), ['sort' => 'total', 'direction' => $filters['sort'] === 'total' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Total{{ $filters['sort'] === 'total' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('invoices.index', array_merge(request()->query(), ['sort' => 'due_date', 'direction' => $filters['sort'] === 'due_date' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Vence{{ $filters['sort'] === 'due_date' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($invoices as $invoice)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono font-medium">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="hover:underline">{{ $invoice->number }}</a>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $invoice->company?->trade_name ?? $invoice->company_name ?? '—' }}
                                    @if ($invoice->sale)<span class="block font-mono text-xs text-slate-400">{{ $invoice->sale->number }}</span>@endif
                                </td>
                                <td class="px-4 py-2">
                                    <x-status-badge :status="$invoice->status" />
                                    @if ($invoice->is_overdue)<span class="ml-1"><x-badge color="yellow">Vencida</x-badge></span>@endif
                                </td>
                                <td class="px-4 py-2 font-medium">{{ number_format($invoice->total, 2) }} {{ $invoice->currency }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-slate-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $invoices->links() }}</div>
        @endif
    </x-card>
@endsection
