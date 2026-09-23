<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }} — {{ config('app.name', 'NexusCRM') }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #1e293b; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 600; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 14px; margin: 1rem 0; }
        .muted { color: #64748b; font-size: 12px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <p class="no-print muted"><a href="{{ route('quotes.show', $quote) }}">← Volver</a> · <a href="#" onclick="window.print(); return false;">Imprimir</a></p>
    <h1>Cotización {{ $quote->number }}</h1>
    <p>Estado: {{ ucfirst($quote->status) }} · Emitida: {{ $quote->issue_date?->format('Y-m-d') ?? '—' }} · Válida hasta: {{ $quote->valid_until?->format('Y-m-d') ?? '—' }}</p>
    <div class="meta">
        <div><strong>Empresa:</strong> {{ $quote->company?->trade_name ?? '—' }}</div>
        <div><strong>Contacto:</strong> @if ($quote->contact){{ $quote->contact->first_name }} {{ $quote->contact->last_name }}@else — @endif</div>
        <div><strong>Oportunidad:</strong> {{ $quote->opportunity?->name ?? '—' }}</div>
        <div><strong>Responsable:</strong> {{ $quote->owner?->name ?? '—' }}</div>
    </div>
    <table>
        <thead>
            <tr><th>#</th><th>Descripción</th><th class="num">Cant.</th><th class="num">P. unit.</th><th class="num">Dto.</th><th class="num">Imp.</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ number_format($item->quantity, 3) }}</td>
                    <td class="num">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="num">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="num">{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6">Subtotal</td><td class="num">{{ number_format($quote->subtotal, 2) }}</td></tr>
            <tr><td colspan="6">Descuento</td><td class="num">{{ number_format($quote->discount_total, 2) }}</td></tr>
            <tr><td colspan="6">Impuesto</td><td class="num">{{ number_format($quote->tax_total, 2) }}</td></tr>
            <tr><td colspan="6">Total {{ $quote->currency }}</td><td class="num">{{ number_format($quote->total, 2) }}</td></tr>
        </tfoot>
    </table>
    @if ($quote->notes)<p><strong>Notas:</strong> {{ $quote->notes }}</p>@endif
    @if ($quote->terms)<p><strong>Términos:</strong> {{ $quote->terms }}</p>@endif
</body>
</html>
