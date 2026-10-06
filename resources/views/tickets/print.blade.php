<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $ticket->number }} — {{ config('app.name', 'NexusCRM') }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #1e293b; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; font-size: 14px; margin: 1rem 0; }
        .muted { color: #64748b; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: #e2e8f0; color: #334155; }
        .section-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin: 1rem 0; font-size: 14px; }
        .msg { border-left: 3px solid #cbd5e1; padding-left: 10px; margin-bottom: 12px; font-size: 13px; }
        .msg-internal { border-left-color: #f59e0b; background-color: #fffbeb; padding: 6px 10px; border-radius: 0 4px 4px 0; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <p class="no-print muted"><a href="{{ route('tickets.show', $ticket) }}">← Volver</a> · <a href="#" onclick="window.print(); return false;">Imprimir</a></p>
    <h1>Ticket de Soporte {{ $ticket->number }}</h1>
    <p>
        <span class="badge">Estado: {{ ucfirst($ticket->status) }}</span> ·
        <span class="badge">Prioridad: {{ ucfirst($ticket->priority) }}</span> ·
        <span class="badge">Canal: {{ ucfirst($ticket->channel ?? 'web') }}</span>
    </p>

    <div class="meta">
        <div><strong>Asunto:</strong> {{ $ticket->subject }}</div>
        <div><strong>Categoría:</strong> {{ $ticket->category?->name ?? 'Sin categoría' }}</div>
        <div><strong>Empresa:</strong> {{ $ticket->company?->trade_name ?? '—' }}</div>
        <div><strong>Contacto:</strong> {{ $ticket->contact ? "{$ticket->contact->first_name} {$ticket->contact->last_name}" : ($ticket->requester_name ?? '—') }}</div>
        <div><strong>Asignado a:</strong> {{ $ticket->assignee?->name ?? 'Sin asignar' }}</div>
        <div><strong>Creado por:</strong> {{ $ticket->creator?->name ?? 'Sistema' }} ({{ $ticket->created_at->format('Y-m-d H:i') }})</div>
    </div>

    @if ($ticket->description)
        <div class="section-box">
            <h3 style="margin-top: 0; font-size: 14px; text-transform: uppercase; color: #475569;">Descripción del caso</h3>
            <div style="white-space: pre-wrap;">{{ $ticket->description }}</div>
        </div>
    @endif

    <div class="section-box">
        <h3 style="margin-top: 0; font-size: 14px; text-transform: uppercase; color: #475569;">Historial de mensajes ({{ $ticket->messages->count() }})</h3>
        @forelse ($ticket->messages as $msg)
            <div class="msg {{ $msg->is_internal ? 'msg-internal' : '' }}">
                <div class="muted">
                    <strong>{{ $msg->user?->name ?? $msg->contact?->first_name ?? 'Sistema' }}</strong> ·
                    {{ $msg->created_at->format('Y-m-d H:i') }}
                    @if ($msg->is_internal) · <em>[Nota interna]</em> @endif
                </div>
                <div style="margin-top: 4px; white-space: pre-wrap;">{{ $msg->body }}</div>
            </div>
        @empty
            <p class="muted">No hay mensajes registrados en este ticket.</p>
        @endforelse
    </div>
</body>
</html>
