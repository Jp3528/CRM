@extends('layouts.app', ['header' => 'Generar factura', 'subheader' => $sale->number])

@section('title', 'Generar factura')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ventas', 'url' => route('sales.index')], ['label' => $sale->number, 'url' => route('sales.show', $sale)], ['label' => 'Facturar']]" />
@endsection

@section('content')
    <x-card title="Generar factura interna" subtitle="Copia snapshots y totales de la venta. Documento interno, no fiscal.">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">Venta</dt><dd class="font-mono font-medium">{{ $sale->number }}</dd></div>
            <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">{{ $sale->company?->trade_name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Total</dt><dd class="font-medium">{{ number_format($sale->total, 2) }} {{ $sale->currency }}</dd></div>
            <div><dt class="text-slate-500">Líneas</dt><dd class="font-medium">{{ $sale->items->count() }}</dd></div>
        </dl>

        <form method="POST" action="{{ route('sales.invoice.store', $sale) }}" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <x-label for="due_date" value="Vencimiento" />
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                <x-input-error :message="$errors->get('due_date')[0] ?? null" />
            </div>
            <div>
                <x-label for="notes" value="Notas" />
                <input id="notes" name="notes" type="text" value="{{ old('notes') }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2 md:col-span-2">
                <x-button>Generar factura</x-button>
                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
