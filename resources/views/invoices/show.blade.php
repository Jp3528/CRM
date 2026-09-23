@extends('layouts.app', ['header' => $invoice->number, 'subheader' => 'Factura interna — documento no fiscal'])

@section('title', $invoice->number)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Facturas', 'url' => route('invoices.index')], ['label' => $invoice->number]]" />
@endsection

@section('content')
    <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-2 text-sm text-yellow-800">
        Documento interno de control. No tiene validez fiscal ni sustituye facturación electrónica oficial.
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$invoice->status" />
        @if ($invoice->is_overdue)<x-badge color="yellow">Vencida por fecha</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="text-slate-700 hover:underline">Imprimir</a>
            @can('delete', $invoice)
                <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $invoice->number }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    @if ($canUpdate)
        <x-card title="Estado" subtitle="Registro administrativo interno (sin pagos reales)">
            <div class="flex flex-wrap gap-2">
                @if ($invoice->status === 'draft')
                    <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Marcar enviada</x-button>
                    </form>
                @endif
                @if (in_array($invoice->status, ['draft', 'sent'], true))
                    <form method="POST" action="{{ route('invoices.pay', $invoice) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button>Marcar pagada</x-button>
                    </form>
                    <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="danger">Cancelar</x-button>
                    </form>
                @endif
            </div>
            @if ($errors->has('status'))
                <x-input-error :message="$errors->get('status')[0]" />
            @endif
        </x-card>
    @endif

    <x-card title="Factura {{ $invoice->number }}">
        <dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div><dt class="text-slate-500">Venta origen</dt><dd class="font-medium">@if ($invoice->sale)<a href="{{ route('sales.show', $invoice->sale) }}" class="font-mono hover:underline">{{ $invoice->sale->number }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($invoice->company)<a href="{{ route('companies.show', $invoice->company) }}" class="hover:underline">{{ $invoice->company_name ?? $invoice->company->trade_name }}</a>@else {{ $invoice->company_name ?? '—' }}@endif</dd></div>
            <div><dt class="text-slate-500">Contacto</dt><dd class="font-medium">@if ($invoice->contact)<a href="{{ route('contacts.show', $invoice->contact) }}" class="hover:underline">{{ $invoice->contact_name ?? $invoice->contact->first_name }}</a>@else {{ $invoice->contact_name ?? '—' }}@endif</dd></div>
            <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $invoice->owner?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Moneda</dt><dd class="font-medium">{{ $invoice->currency }}</dd></div>
            <div><dt class="text-slate-500">Emisión</dt><dd class="font-medium">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Vencimiento</dt><dd class="font-medium">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Pagada</dt><dd class="font-medium">{{ $invoice->paid_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($invoice->company_tax_id || $invoice->company_address)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">
                Snapshot fiscal histórico: {{ $invoice->company_tax_id ?? 'sin NIT' }} · {{ $invoice->company_address ?? 'sin dirección' }}
                @if ($invoice->contact_email)<span> · {{ $invoice->contact_email }}</span>@endif
            </div>
        @endif
        @if ($invoice->notes)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $invoice->notes }}</p></div>
        @endif
    </x-card>

    <x-card title="Líneas ({{ $invoice->items->count() }})">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Descripción</th><th class="px-3 py-2 text-right">Cant.</th><th class="px-3 py-2 text-right">P. unit.</th><th class="px-3 py-2 text-right">Descuento</th><th class="px-3 py-2 text-right">Impuesto</th><th class="px-3 py-2 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($invoice->items as $i => $item)
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
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Subtotal</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($invoice->subtotal, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Descuento</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($invoice->discount_total, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right text-slate-500">Impuesto</td><td class="px-3 py-1.5 text-right font-medium">{{ number_format($invoice->tax_total, 2) }}</td></tr>
                    <tr><td colspan="6" class="px-3 py-1.5 text-right font-semibold">Total {{ $invoice->currency }}</td><td class="px-3 py-1.5 text-right font-semibold">{{ number_format($invoice->total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-400">Creada {{ $invoice->created_at->format('Y-m-d H:i') }} · Actualizada {{ $invoice->updated_at->format('Y-m-d H:i') }}</p>
    </x-card>
@endsection
