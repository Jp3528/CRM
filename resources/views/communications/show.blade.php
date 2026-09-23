@extends('layouts.app', ['header' => $communication->subject ?? 'Comunicación', 'subheader' => 'Registro interno'])

@section('title', 'Comunicación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Comunicaciones', 'url' => route('communications.index')], ['label' => $communication->subject ?? ('#'.$communication->id)]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-badge>{{ ucfirst($communication->channel) }}</x-badge>
        <x-status-badge :status="$communication->status" :label="ucfirst(str_replace('_', ' ', $communication->status))" />
        <x-badge color="slate">{{ ucfirst($communication->direction) }}</x-badge>
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate && in_array($communication->status, ['draft', 'queued'], true))
                <a href="{{ route('communications.edit', $communication) }}" class="text-slate-700 hover:underline">Editar</a>
            @endif
            @can('delete', $communication)
                <form method="POST" action="{{ route('communications.destroy', $communication) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar comunicación?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    @if ($canSimulate)
        <x-card title="Envío simulado" subtitle="No se envía nada externo; solo se registra internamente">
            <form method="POST" action="{{ route('communications.simulate', $communication) }}" class="flex items-center gap-2">
                @csrf @method('PATCH')
                <x-button>Registrar envío simulado</x-button>
                <span class="text-xs text-slate-400">status → simulated_sent · sent_at → ahora</span>
            </form>
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Contenido (escapado)">
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-slate-500">Asunto</dt><dd class="font-medium">{{ $communication->subject ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Mensaje</dt><dd class="mt-1 whitespace-pre-line rounded-md border border-slate-200 bg-slate-50 px-3 py-2">{{ $communication->body }}</dd></div>
                @if ($communication->template)<div><dt class="text-slate-500">Plantilla</dt><dd class="font-medium">{{ $communication->template->name }}</dd></div>@endif
            </dl>
        </x-card>

        <x-card title="Objetivo y trazabilidad">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Campaña</dt><dd class="font-medium">@if ($communication->campaign && $canViewCampaign)<a href="{{ route('campaigns.show', $communication->campaign) }}" class="hover:underline">{{ $communication->campaign->name }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $communication->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Contacto</dt><dd class="font-medium">@if ($communication->contact && $canViewContact)<a href="{{ route('contacts.show', $communication->contact) }}" class="hover:underline">{{ trim($communication->contact->first_name.' '.$communication->contact->last_name) }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Lead</dt><dd class="font-medium">@if ($communication->lead && $canViewLead)<a href="{{ route('leads.show', $communication->lead) }}" class="hover:underline">{{ trim($communication->lead->first_name.' '.$communication->lead->last_name) }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Enviada (simulada)</dt><dd class="font-medium">{{ $communication->sent_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Creada por</dt><dd class="font-medium">{{ $communication->creator?->name ?? '—' }}</dd></div>
            </dl>
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $communication->created_at->format('Y-m-d H:i') }} · Actualizada {{ $communication->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>
    </div>
@endsection
