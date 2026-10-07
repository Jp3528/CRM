@extends('layouts.app', ['header' => 'Oportunidades', 'subheader' => 'Pipeline comercial'])

@section('title', 'Oportunidades')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Oportunidades']]" />
@endsection

@section('content')
    <x-card title="Oportunidades" subtitle="{{ $opportunities->total() }} registro(s)">
        <div class="mb-3 flex gap-2 text-sm">
            <a href="{{ route('opportunities.kanban', request()->only('pipeline_id')) }}" class="text-slate-700 hover:underline">Ver Kanban →</a>
        </div>
        <form method="GET" action="{{ route('opportunities.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, empresa, contacto…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-3 lg:col-span-3">
            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="pipeline_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todos los pipelines</option>
                @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected((string) $filters['pipeline_id'] === (string) $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="owner_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-2">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <select name="company_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm lg:col-span-3">
                <option value="">Todas las empresas</option>
                @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $filters['company_id'] === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
            </select>
            <div class="sm:col-span-2 md:col-span-3 lg:col-span-3 grid grid-cols-2 gap-2">
                <input type="number" step="0.01" min="0" name="amount_min" value="{{ $filters['amount_min'] }}" placeholder="Monto mín" title="Monto mínimo" aria-label="Monto mínimo"
                    class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
                <input type="number" step="0.01" min="0" name="amount_max" value="{{ $filters['amount_max'] }}" placeholder="Monto máx" title="Monto máximo" aria-label="Monto máximo"
                    class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
            </div>
            <div class="col-span-full sm:col-span-2 md:col-span-3 lg:col-span-9 flex flex-wrap items-center gap-2">
                <x-button>Buscar</x-button>
                <a href="{{ route('opportunities.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'opportunities'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Opportunity::class)
                    <a href="{{ route('opportunities.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva oportunidad</a>
                @endcan
            </div>
        </form>

        @if ($opportunities->isEmpty())
            <x-empty-state title="No hay oportunidades registradas." message="Crea la primera oportunidad del pipeline comercial."
                :action-url="auth()->user()->can('create', App\Models\Opportunity::class) ? route('opportunities.create') : null" action-label="Nueva oportunidad" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('opportunities.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $filters['sort'] === 'name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre{{ $filters['sort'] === 'name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Empresa / Contacto</th>
                            <th class="px-4 py-2 text-left">Etapa</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('opportunities.index', array_merge(request()->query(), ['sort' => 'amount', 'direction' => $filters['sort'] === 'amount' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Valor{{ $filters['sort'] === 'amount' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('opportunities.index', array_merge(request()->query(), ['sort' => 'probability', 'direction' => $filters['sort'] === 'probability' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Prob.{{ $filters['sort'] === 'probability' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Ponderado</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('opportunities.index', array_merge(request()->query(), ['sort' => 'expected_close_date', 'direction' => $filters['sort'] === 'expected_close_date' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Cierre prev.{{ $filters['sort'] === 'expected_close_date' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('opportunities.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($opportunities as $opp)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('opportunities.show', $opp) }}" class="hover:underline">{{ $opp->name }}</a>
                                    <span class="block text-xs font-normal text-slate-400">{{ $opp->pipeline?->name }}</span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $opp->company?->trade_name ?? '—' }}
                                    @if ($opp->contact)<span class="block text-xs">{{ $opp->contact->first_name }} {{ $opp->contact->last_name }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->stage?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->amount !== null ? number_format($opp->amount, 2).' '.$opp->currency : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->probability !== null ? $opp->probability.'%' : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->weighted_amount !== null ? number_format($opp->weighted_amount, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $opp->expected_close_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$opp->status" /></td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('opportunities.show', $opp) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $opp) · <a href="{{ route('opportunities.edit', $opp) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $opportunities->links() }}</div>
        @endif
    </x-card>
@endsection
