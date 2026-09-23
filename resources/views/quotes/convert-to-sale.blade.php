@extends('layouts.app', ['header' => 'Crear venta desde cotización', 'subheader' => $quote->number])

@section('title', 'Crear venta')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Cotizaciones', 'url' => route('quotes.index')], ['label' => $quote->number, 'url' => route('quotes.show', $quote)], ['label' => 'Crear venta']]" />
@endsection

@section('content')
    <x-card title="Convertir {{ $quote->number }} en venta" subtitle="Copia snapshots y totales exactos. 1 cotización → máximo 1 venta.">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">{{ $quote->company?->trade_name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Total</dt><dd class="font-medium">{{ number_format($quote->total, 2) }} {{ $quote->currency }}</dd></div>
            <div><dt class="text-slate-500">Líneas</dt><dd class="font-medium">{{ $quote->items->count() }}</dd></div>
            <div><dt class="text-slate-500">Estado</dt><dd><x-status-badge :status="$quote->status" /></dd></div>
        </dl>

        <form method="POST" action="{{ route('quotes.sale.store', $quote) }}" class="mt-4 grid gap-4 md:grid-cols-3">
            @csrf
            <div>
                <x-label for="sale_date" value="Fecha de venta" />
                <input id="sale_date" name="sale_date" type="date" value="{{ old('sale_date', now()->format('Y-m-d')) }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <x-label for="owner_id" value="Responsable (vacío = el de la cotización)" />
                <select id="owner_id" name="owner_id"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Mantener ({{ $quote->owner?->name ?? 'ninguno' }}) —</option>
                    @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
                </select>
                <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
            </div>
            <div>
                <x-label for="notes" value="Notas" />
                <input id="notes" name="notes" type="text" value="{{ old('notes') }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex gap-2 md:col-span-3">
                <x-button>Crear venta</x-button>
                <a href="{{ route('quotes.show', $quote) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
