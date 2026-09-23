@extends('layouts.app', ['header' => $sale->number, 'subheader' => 'Documento de venta'])

@section('title', $sale->number)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ventas', 'url' => route('sales.index')], ['label' => $sale->number]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$sale->status" />
        @if ($sale->quote)<x-badge>Desde {{ $sale->quote->number }}</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            <a href="{{ route('sales.print', $sale) }}" target="_blank" class="text-slate-700 hover:underline">Imprimir</a>
            @if ($canUpdate && $editable)<a href="{{ route('sales.edit', $sale) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('delete', $sale)
                <form method="POST" action="{{ route('sales.destroy', $sale) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $sale->number }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    @if ($canUpdate)
        <x-card title="Estado" subtitle="Transiciones con trazabilidad">
            <div class="flex flex-wrap gap-2">
                @if ($sale->status === 'draft')
                    <form method="POST" action="{{ route('sales.confirm', $sale) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Confirmar</x-button>
                    </form>
                @endif
                @if ($sale->status === 'confirmed')
                    <form method="POST" action="{{ route('sales.complete', $sale) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button>Completar</x-button>
                    </form>
                @endif
                @if (in_array($sale->status, ['draft', 'confirmed'], true))
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="danger">Cancelar</x-button>
                    </form>
                @endif
                @if (! $sale->invoice && in_array($sale->status, ['confirmed', 'completed'], true))
                    <a href="{{ route('sales.invoice.create', $sale) }}" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Generar factura</a>
                @endif
            </div>
            @if ($errors->has('status'))
                <x-input-error :message="$errors->get('status')[0]" />
            @endif
        </x-card>
    @endif

    <x-card title="Venta {{ $sale->number }}">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">Cotización origen</dt><dd class="font-medium">@if ($sale->quote)<a href="{{ route('quotes.show', $sale->quote) }}" class="font-mono hover:underline">{{ $sale->quote->number }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($sale->company)<a href="{{ route('companies.show', $sale->company) }}" class="hover:underline">{{ $sale->company->trade_name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Contacto</dt><dd class="font-medium">@if ($sale->contact)<a href="{{ route('contacts.show', $sale->contact) }}" class="hover:underline">{{ $sale->contact->first_name }} {{ $sale->contact->last_name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Oportunidad</dt><dd class="font-medium">@if ($sale->opportunity)<a href="{{ route('opportunities.show', $sale->opportunity) }}" class="hover:underline">{{ $sale->opportunity->name }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $sale->owner?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Moneda</dt><dd class="font-medium">{{ $sale->currency }}</dd></div>
            <div><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $sale->sale_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Completada / Cancelada</dt><dd class="font-medium">{{ $sale->completed_at?->format('Y-m-d H:i') ?? $sale->cancelled_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($sale->notes)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $sale->notes }}</p></div>
        @endif
    </x-card>

    <x-card title="Líneas ({{ $sale->items->count() }})">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Producto / Descripción</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">P. unit.</th><th class="px-3 py-2 text-right">Descuento</th><th class="px-3 py-2 text-right">Impuesto</th><th class="px-3 py-2 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($sale->items as $i => $item)
                        <tr>
                            <td class="px-3 py-2 text-slate-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ $item->description }}</span>
                                @if ($item->sku)<span class="block font-mono text-xs text-slate-400">{{ $item->sku }} · {{ ucfirst($item->unit) }}</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->quantity, 3) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->discount_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($item->tax_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Subtotal</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($sale->subtotal, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Descuento</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($sale->discount_total, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Impuesto</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($sale->tax_total, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right font-semibold">Total {{ $sale->currency }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ number_format($sale->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-400">Creada {{ $sale->created_at->format('Y-m-d H:i') }} · Actualizada {{ $sale->updated_at->format('Y-m-d H:i') }}</p>
    </x-card>

    <x-card title="Factura interna" subtitle="1 venta → máximo 1 factura">
        @if ($sale->invoice)
            <p class="text-sm">
                <a href="{{ route('invoices.show', $sale->invoice) }}" class="font-mono font-medium hover:underline">{{ $sale->invoice->number }}</a>
                <span class="ml-2"><x-status-badge :status="$sale->invoice->status" /></span>
                <span class="ml-2 text-slate-600">{{ number_format($sale->invoice->total, 2) }} {{ $sale->invoice->currency }}</span>
            </p>
        @else
            <p class="text-sm text-slate-500">Sin factura.
                @if ($canCreateInvoice && in_array($sale->status, ['confirmed', 'completed'], true))
                    <a href="{{ route('sales.invoice.create', $sale) }}" class="hover:underline">Generar factura</a>
                @endif
            </p>
        @endif
    </x-card>
@endsection
