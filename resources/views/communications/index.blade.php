@extends('layouts.app', ['header' => 'Comunicaciones', 'subheader' => 'Registro interno (sin envíos externos)'])

@section('title', 'Comunicaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Comunicaciones']]" />
@endsection

@section('content')
    <x-card title="Comunicaciones" subtitle="{{ $communications->total() }} registro(s) · solo objetivos en tu alcance">
        <form method="GET" action="{{ route('communications.index') }}" class="mb-4 grid gap-2 md:grid-cols-5">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar asunto o cuerpo…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
            </select>
            <select name="channel" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los canales</option>
                @foreach ($channels as $c)<option value="{{ $c }}" @selected($filters['channel'] === $c)>{{ ucfirst($c) }}</option>@endforeach
            </select>
            <select name="campaign_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las campañas</option>
                @foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected((string) $filters['campaign_id'] === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-5">
                <x-button>Buscar</x-button>
                <a href="{{ route('communications.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Communication::class)
                    <a href="{{ route('communications.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva comunicación</a>
                @endcan
            </div>
        </form>

        @if ($communications->isEmpty())
            <x-empty-state title="No hay comunicaciones." message="Registra la primera comunicación interna/simulada."
                :action-url="auth()->user()->can('create', App\Models\Communication::class) ? route('communications.create') : null" action-label="Nueva comunicación" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Asunto</th>
                            <th class="px-4 py-2 text-left">Canal / Estado</th>
                            <th class="px-4 py-2 text-left">Objetivo</th>
                            <th class="px-4 py-2 text-left">Campaña</th>
                            <th class="px-4 py-2 text-left">Enviada</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($communications as $comm)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2"><a href="{{ route('communications.show', $comm) }}" class="font-medium hover:underline">{{ $comm->subject ?? '(sin asunto)' }}</a></td>
                                <td class="px-4 py-2"><x-badge>{{ ucfirst($comm->channel) }}</x-badge> <x-status-badge :status="$comm->status" :label="ucfirst(str_replace('_', ' ', $comm->status))" /></td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($comm->contact){{ trim($comm->contact->first_name.' '.$comm->contact->last_name) }}
                                    @elseif ($comm->lead){{ trim($comm->lead->first_name.' '.$comm->lead->last_name) }}
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $comm->campaign?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $comm->sent_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('communications.show', $comm) }}" class="text-slate-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $communications->links() }}</div>
        @endif
    </x-card>
@endsection
