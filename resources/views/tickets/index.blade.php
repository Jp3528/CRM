@extends('layouts.app', ['header' => 'Tickets', 'subheader' => 'Soporte al cliente'])

@section('title', 'Tickets')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tickets']]" />
@endsection

@section('content')
    <x-card title="Tickets de soporte" subtitle="{{ $tickets->total() }} registro(s)">
        <div class="mb-3 flex flex-wrap gap-2 text-sm">
            @foreach (['mine' => 'Mis tickets', 'unassigned' => 'Sin asignar', 'open' => 'Abiertos', 'pending' => 'Pendientes', 'urgent' => 'Urgentes'] as $p => $l)
                <a href="{{ route('tickets.index', ['preset' => $p]) }}"
                   class="rounded-md px-2.5 py-1 {{ ($filters['preset'] ?? '') === $p ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $l }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('tickets.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar número, asunto, empresa, solicitante…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="priority" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las prioridades</option>
                @foreach ($priorities as $p)<option value="{{ $p }}" @selected($filters['priority'] === $p)>{{ ucfirst($p) }}</option>@endforeach
            </select>
            <select name="category_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) $filters['category_id'] === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
            <select name="assigned_to" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los asignados</option>
                <option value="unassigned" @selected($filters['assigned_to'] === 'unassigned')>Sin asignar</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) $filters['assigned_to'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <select name="company_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $filters['company_id'] === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Ticket::class)
                    <a href="{{ route('tickets.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo ticket</a>
                @endcan
            </div>
        </form>

        @if ($tickets->isEmpty())
            <x-empty-state title="No hay tickets registrados." message="Crea el primer caso de soporte."
                :action-url="auth()->user()->can('create', App\Models\Ticket::class) ? route('tickets.create') : null" action-label="Nuevo ticket" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tickets.index', array_merge(request()->query(), ['sort' => 'number', 'direction' => $filters['sort'] === 'number' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Número{{ $filters['sort'] === 'number' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Asunto / Empresa</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tickets.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tickets.index', array_merge(request()->query(), ['sort' => 'priority', 'direction' => $filters['sort'] === 'priority' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Prioridad{{ $filters['sort'] === 'priority' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Asignado</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tickets.index', array_merge(request()->query(), ['sort' => 'last_reply_at', 'direction' => $filters['sort'] === 'last_reply_at' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Última actividad{{ $filters['sort'] === 'last_reply_at' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($tickets as $ticket)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono font-medium">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="hover:underline">{{ $ticket->number }}</a>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium hover:underline">{{ $ticket->subject }}</a>
                                    <span class="block text-xs text-slate-500">{{ $ticket->company?->trade_name ?? $ticket->requester_label }} · {{ $ticket->category?->name ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-2"><x-status-badge :status="$ticket->status" :label="ucfirst($ticket->status)" /></td>
                                <td class="px-4 py-2"><x-badge color="{{ $ticket->priority === 'urgent' ? 'red' : ($ticket->priority === 'high' ? 'yellow' : 'slate') }}">{{ ucfirst($ticket->priority) }}</x-badge></td>
                                <td class="px-4 py-2 text-slate-600">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ ($ticket->last_reply_at ?? $ticket->updated_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $ticket) · <a href="{{ route('tickets.edit', $ticket) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $tickets->links() }}</div>
        @endif
    </x-card>
@endsection
