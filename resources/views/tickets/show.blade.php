@extends('layouts.app', ['header' => $ticket->number, 'subheader' => $ticket->subject])

@section('title', $ticket->number)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tickets', 'url' => route('tickets.index')], ['label' => $ticket->number]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$ticket->status" :label="ucfirst($ticket->status)" />
        <x-badge color="{{ $ticket->priority === 'urgent' ? 'red' : ($ticket->priority === 'high' ? 'yellow' : 'slate') }}">{{ ucfirst($ticket->priority) }}</x-badge>
        @if ($ticket->category)<x-badge>{{ $ticket->category->name }}</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            <a href="{{ route('tickets.print', $ticket) }}" target="_blank" class="text-slate-700 hover:underline">Imprimir</a>
            @if ($canUpdate)<a href="{{ route('tickets.edit', $ticket) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('delete', $ticket)
                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $ticket->number }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    @if ($canUpdate)
        <x-card title="Estado" subtitle="Transiciones validadas con historial">
            <div class="flex flex-wrap gap-2">
                @if (in_array($ticket->status, ['new', 'pending'], true))
                    <form method="POST" action="{{ route('tickets.open', $ticket) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Atender</x-button>
                    </form>
                @endif
                @if (in_array($ticket->status, ['new', 'open'], true))
                    <form method="POST" action="{{ route('tickets.pending', $ticket) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Pendiente</x-button>
                    </form>
                @endif
                @if (in_array($ticket->status, ['open', 'pending'], true))
                    <form method="POST" action="{{ route('tickets.resolve', $ticket) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button>Resolver</x-button>
                    </form>
                @endif
                @if ($ticket->status === 'resolved')
                    <form method="POST" action="{{ route('tickets.close', $ticket) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Cerrar</x-button>
                    </form>
                @endif
                @if (in_array($ticket->status, ['resolved', 'closed'], true))
                    <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-button variant="secondary">Reabrir</x-button>
                    </form>
                @endif
            </div>
            @if ($errors->has('status'))
                <x-input-error :message="$errors->get('status')[0]" />
            @endif
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos del caso">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Solicitante</dt><dd class="font-medium">{{ $ticket->requester_label }}@if ($ticket->requester_email && ! $ticket->contact)<span class="block text-xs font-normal text-slate-500">{{ $ticket->requester_email }}</span>@endif</dd></div>
                <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($ticket->company && $canViewCompany)<a href="{{ route('companies.show', $ticket->company) }}" class="hover:underline">{{ $ticket->company->trade_name }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Canal</dt><dd class="font-medium">{{ ucfirst($ticket->channel) }}</dd></div>
                <div><dt class="text-slate-500">Asignado a</dt><dd class="font-medium">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</dd></div>
                <div><dt class="text-slate-500">Creado por</dt><dd class="font-medium">{{ $ticket->creator?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Antigüedad</dt><dd class="font-medium">{{ $ticket->age_for_humans }}</dd></div>
            </dl>
            @if ($ticket->description)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción inicial</p><p class="mt-1 whitespace-pre-line">{{ $ticket->description }}</p></div>
            @endif
        </x-card>

        <x-card title="Tiempos" subtitle="Métricas operativas básicas (informativas)">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Creado</dt><dd class="font-medium">{{ $ticket->created_at->format('Y-m-d H:i') }}</dd></div>
                <div><dt class="text-slate-500">Primera respuesta</dt><dd class="font-medium">{{ $ticket->first_response_at?->format('Y-m-d H:i') ?? 'Sin responder' }}</dd></div>
                <div><dt class="text-slate-500">Resuelto</dt><dd class="font-medium">{{ $ticket->resolved_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Cerrado</dt><dd class="font-medium">{{ $ticket->closed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">T. primera respuesta</dt><dd class="font-medium">{{ $ticket->first_response_seconds !== null ? gmdate('H:i:s', $ticket->first_response_seconds) : '—' }}</dd></div>
                <div><dt class="text-slate-500">T. resolución</dt><dd class="font-medium">{{ $ticket->resolution_seconds !== null ? gmdate('H:i:s', $ticket->resolution_seconds) : '—' }}</dd></div>
            </dl>
        </x-card>
    </div>

    <x-card title="Conversación ({{ $ticket->messages->count() }})" subtitle="Respuestas, notas internas y eventos">
        <ol class="space-y-3">
            @forelse ($ticket->messages as $message)
                <li class="rounded-md border px-4 py-3 text-sm {{ $message->is_system ? 'border-slate-200 bg-slate-50' : ($message->is_internal ? 'border-yellow-200 bg-yellow-50/50' : 'border-slate-200 bg-white') }}">
                    <p class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if ($message->is_system)
                            <x-badge color="slate">Sistema</x-badge>
                        @elseif ($message->is_internal)
                            <x-badge color="yellow">Nota interna</x-badge>
                        @else
                            <x-badge color="blue">Respuesta</x-badge>
                        @endif
                        <span class="font-medium text-slate-700">{{ $message->user?->name ?? $message->contact?->first_name ?? 'Sistema' }}</span>
                        <span>{{ $message->created_at->format('Y-m-d H:i') }}</span>
                    </p>
                    <p class="whitespace-pre-line text-slate-800">{{ $message->body }}</p>
                </li>
            @empty
                <p class="text-sm text-slate-500">Sin mensajes todavía.</p>
            @endforelse
        </ol>

        @if ($canUpdate && $ticket->status !== 'closed')
            <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 md:grid-cols-2">
                <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}">
                    @csrf
                    <input type="hidden" name="type" value="reply">
                    <x-label for="reply-body" value="Responder (conversación)" />
                    <textarea id="reply-body" name="body" rows="3" required
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                    <div class="mt-2"><x-button>Enviar respuesta</x-button></div>
                </form>
                <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}">
                    @csrf
                    <input type="hidden" name="type" value="note">
                    <x-label for="note-body" value="Nota interna (no visible al cliente)" />
                    <textarea id="note-body" name="body" rows="3" required
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                    <div class="mt-2"><x-button variant="secondary">Guardar nota</x-button></div>
                </form>
            </div>
            @if ($errors->has('body') || $errors->has('type'))
                <x-input-error :message="$errors->get('body')[0] ?? $errors->get('type')[0]" />
            @endif
        @elseif ($ticket->status === 'closed')
            <p class="mt-4 text-sm text-slate-500">Ticket cerrado. Reábrelo para continuar la conversación.</p>
        @endif
    </x-card>
@endsection
