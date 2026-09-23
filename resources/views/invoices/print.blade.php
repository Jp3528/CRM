<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }} — {{ config('app.name', 'NexusCRM') }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #1e293b; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 600; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 14px; margin: 1rem 0; }
        .muted { color: #64748b; font-size: 12px; }
        .notice { border: 1px solid #facc15; background: #fefce8; padding: 8px 12px; font-size: 13px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <p class="no-print muted"><a href="{{ route('invoices.show', $invoice) }}">← Volver</a> · <a href="#" onclick="window.print(); return false;">Imprimir</a></p>
    <h1>Factura interna {{ $invoice->number }}</h1>
    <p class="notice">Documento interno de control. Sin validez fiscal.</p>
    <p>Estado: {{ ucfirst($invoice->status) }} · Emitida: {{ $invoice->issue_date?->format('Y-m-d') ?? '—' }} · Vence: {{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</p>
    <div class="meta">
        <div><strong>Empresa:</strong> {{ $invoice->company_name ?? $invoice->company?->trade_name ?? '—' }}</div>
        <div><strong>NIT:</strong> {{ $invoice->company_tax_id ?? '—' }}</div>
        <div><strong>Dirección:</strong> {{ $invoice->company_address ?? '—' }}</div>
        <div><strong>Contacto:</strong> {{ $invoice->contact_name ?? '—' }} {{ $invoice->contact_email ? '('.$invoice->contact_email.')' : '' }}</div>
        <div><strong>Venta origen:</strong> {{ $invoice->sale?->number ?? '—' }}</div>
        <div><strong>Responsable:</strong> {{ $invoice->owner?->name ?? '—' }}</div>
    </div>
    <table>
        <thead>
            <tr><th>#</th><th>Descripción</th><th class="num">Cant.</th><th class="num">P. unit.</th><th class="num">Dto.</th><th class="num">Imp.</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
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
            <tr><td colspan="6">Subtotal</td><td class="num">{{ number_format($invoice->subtotal, 2) }}</td></tr>
            <tr><td colspan="6">Descuento</td><td class="num">{{ number_format($invoice->discount_total, 2) }}</td></tr>
            <tr><td colspan="6">Impuesto</td><td class="num">{{ number_format($invoice->tax_total, 2) }}</td></tr>
            <tr><td colspan="6">Total {{ $invoice->currency }}</td><td class="num">{{ number_format($invoice->total, 2) }}</td></tr>
        </tfoot>
    </table>
    @if ($invoice->notes)<p><strong>Notas:</strong> {{ $invoice->notes }}</p>@endif
</body>
</html>
