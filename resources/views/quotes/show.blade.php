@extends('layouts.app', ['header' => $quote->number, 'subheader' => 'Documento comercial'])

@section('title', $quote->number)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Cotizaciones', 'url' => route('quotes.index')], ['label' => $quote->number]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$quote->status" />
        @if ($quote->is_expired)<x-badge color="yellow">Vencida por fecha</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            <a href="{{ route('quotes.print', $quote) }}" target="_blank" class="text-slate-700 hover:underline">Imprimir</a>
            @if ($canUpdate && $editable)<a href="{{ route('quotes.edit', $quote) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @if ($sale)
                <a href="{{ route('sales.show', $sale) }}" class="font-medium text-slate-900 hover:underline">Ver venta {{ $sale->number }}</a>
            @elseif ($quote->status === 'accepted' && $canCreateSale)
                <a href="{{ route('quotes.sale.create', $quote) }}" class="font-medium text-slate-900 hover:underline">Crear venta →</a>
            @endif
            @can('delete', $quote)
                <form method="POST" action="{{ route('quotes.destroy', $quote) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $quote->number }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    @if ($canUpdate && $editable)
        <x-card title="Estado" subtitle="Transiciones con trazabilidad (sin email real)">
            <div class="flex flex-wrap gap-2">
                @if ($quote->status === 'draft')
                    <form method="POST" action="{{ route('quotes.send', $quote) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Marcar como enviada</x-button>
                    </form>
                @endif
                @if (in_array($quote->status, ['draft', 'sent'], true))
                    <form method="POST" action="{{ route('quotes.accept', $quote) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button>Aceptar</x-button>
                    </form>
                    <form method="POST" action="{{ route('quotes.reject', $quote) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="danger">Rechazar</x-button>
                    </form>
                @endif
            </div>
        </x-card>
    @endif

    <x-card title="Cotización {{ $quote->number }}">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($quote->company)<a href="{{ route('companies.show', $quote->company) }}" class="hover:underline">{{ $quote->company->trade_name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Contacto</dt><dd class="font-medium">@if ($quote->contact)<a href="{{ route('contacts.show', $quote->contact) }}" class="hover:underline">{{ $quote->contact->first_name }} {{ $quote->contact->last_name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Oportunidad</dt><dd class="font-medium">@if ($quote->opportunity)<a href="{{ route('opportunities.show', $quote->opportunity) }}" class="hover:underline">{{ $quote->opportunity->name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $quote->owner?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Moneda</dt><dd class="font-medium">{{ $quote->currency }}</dd></div>
            <div><dt class="text-slate-500">Emisión</dt><dd class="font-medium">{{ $quote->issue_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Válida hasta</dt><dd class="font-medium">{{ $quote->valid_until?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Aceptada / Rechazada</dt><dd class="font-medium">{{ $quote->accepted_at?->format('Y-m-d H:i') ?? $quote->rejected_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($quote->notes)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $quote->notes }}</p></div>
        @endif
        @if ($quote->terms)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Términos</p><p class="mt-1 whitespace-pre-line">{{ $quote->terms }}</p></div>
        @endif
    </x-card>

    <x-card title="Líneas ({{ $quote->items->count() }})">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Producto / Descripción</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">P. unit.</th><th class="px-3 py-2 text-right">Descuento</th><th class="px-3 py-2 text-right">Impuesto</th><th class="px-3 py-2 text-right">Subtotal</th><th class="px-3 py-2 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($quote->items as $i => $item)
                        <tr>
                            <td class="px-3 py-2 text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ $item->description }}</span>
                                @if ($item->product)<span class="block text-xs text-slate-400">{{ $item->product->sku }} · {{ ucfirst($item->unit) }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->quantity, 3) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ $item->discount_type === 'percentage' ? number_format($item->discount_value, 2).'%' : number_format($item->discount_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->tax_rate, 2) }}%</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->subtotal, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                    <tr><td colspan="7" class="px-3 py-1.5 text-right text-slate-500">Subtotal</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($quote->subtotal, 2) }}</td></tr>
                    <tr><td colspan="7" class="px-3 py-1.5 text-right text-slate-500">Descuento</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($quote->discount_total, 2) }}</td></tr>
                    <tr><td colspan="7" class="px-3 py-1.5 text-right text-slate-500">Impuesto</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($quote->tax_total, 2) }}</td></tr>
                    <tr><td colspan="7" class="px-3 py-1.5 text-right font-semibold">Total {{ $quote->currency }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ number_format($quote->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-400">Valores calculados en backend. Creada {{ $quote->created_at->format('Y-m-d H:i') }} · Actualizada {{ $quote->updated_at->format('Y-m-d H:i') }}</p>
    </x-card>
@endsection
