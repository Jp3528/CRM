@extends('layouts.app', ['header' => 'Campañas', 'subheader' => 'Marketing interno (sin envíos externos)'])

@section('title', 'Campañas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Campañas']]" />
@endsection

@section('content')
    <x-card title="Campañas" subtitle="{{ $campaigns->total() }} registro(s)">
        <form method="GET" action="{{ route('campaigns.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre o descripción…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="type" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los tipos</option>
                @foreach ($types as $t)<option value="{{ $t }}" @selected($filters['type'] === $t)>{{ ucfirst($t) }}</option>@endforeach
            </select>
            <select name="owner_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) $filters['owner_id'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="rounded-md border-slate-300 px-2 py-2 text-sm">
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('campaigns.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Campaign::class)
                    <a href="{{ route('campaigns.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva campaña</a>
                @endcan
            </div>
        </form>

        @if ($campaigns->isEmpty())
            <x-empty-state title="No hay campañas." message="Crea la primera campaña de marketing interno."
                :action-url="auth()->user()->can('create', App\Models\Campaign::class) ? route('campaigns.create') : null" action-label="Nueva campaña" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('campaigns.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $filters['sort'] === 'name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre{{ $filters['sort'] === 'name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Tipo / Estado</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">Inicio / Fin</th>
                            <th class="px-4 py-2 text-right">Miembros</th>
                            <th class="px-4 py-2 text-right">Presupuesto</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($campaigns as $campaign)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2">
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="font-medium hover:underline">{{ $campaign->name }}</a>
                                    <span class="block text-xs text-slate-500">Actualizada {{ $campaign->updated_at->format('Y-m-d') }}</span>
                                </td>
                                <td class="px-4 py-2"><x-badge>{{ ucfirst($campaign->type) }}</x-badge> <x-status-badge :status="$campaign->status" :label="ucfirst($campaign->status)" /></td>
                                <td class="px-4 py-2 text-slate-600">{{ $campaign->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $campaign->start_at?->format('Y-m-d') ?? '—' }} / {{ $campaign->end_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $campaign->scoped_members_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right">{{ $campaign->budget !== null ? number_format($campaign->budget, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $campaign) · <a href="{{ route('campaigns.edit', $campaign) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $campaigns->links() }}</div>
        @endif
    </x-card>
@endsection
