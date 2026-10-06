@extends('layouts.app', ['header' => 'Leads', 'subheader' => 'Prospectos y calificación'])

@section('title', 'Leads')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Leads']]" />
@endsection

@section('content')
    <x-card title="Leads" subtitle="{{ $leads->total() }} registro(s)">
        <form method="GET" action="{{ route('leads.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, empresa, email, teléfono…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="source" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los orígenes</option>
                @foreach ($sources as $s)<option value="{{ $s }}" @selected($filters['source'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="owner_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) $filters['owner_id'] === (string) $o->id)>{{ $o->name }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <input type="number" name="score_min" value="{{ $filters['score_min'] }}" placeholder="Score mín" min="0" max="100"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
                <input type="number" name="score_max" value="{{ $filters['score_max'] }}" placeholder="Score máx" min="0" max="100"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
            </div>
            <select name="converted" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="all" @selected($filters['converted'] === 'all')>Todos</option>
                <option value="no" @selected($filters['converted'] === 'no')>Sin convertir</option>
                <option value="yes" @selected($filters['converted'] === 'yes')>Convertidos</option>
            </select>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('leads.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'leads'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Lead::class)
                    <a href="{{ route('leads.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nuevo lead</a>
                @endcan
            </div>
        </form>

        @if ($leads->isEmpty())
            <x-empty-state title="No hay leads registrados." message="Crea el primer lead para empezar a calificar prospectos."
                :action-url="auth()->user()->can('create', App\Models\Lead::class) ? route('leads.create') : null" action-label="Nuevo lead" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('leads.index', array_merge(request()->query(), ['sort' => 'first_name', 'direction' => $filters['sort'] === 'first_name' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Nombre{{ $filters['sort'] === 'first_name' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Empresa / Contacto</th>
                            <th class="px-4 py-2 text-left">Origen</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('leads.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('leads.index', array_merge(request()->query(), ['sort' => 'score', 'direction' => $filters['sort'] === 'score' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Score{{ $filters['sort'] === 'score' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('leads.index', array_merge(request()->query(), ['sort' => 'estimated_value', 'direction' => $filters['sort'] === 'estimated_value' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Valor est.{{ $filters['sort'] === 'estimated_value' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($leads as $lead)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('leads.show', $lead) }}" class="hover:underline">{{ $lead->full_name }}</a>
                                    @if ($lead->converted_at)<span class="ml-1"><x-badge color="blue">Convertido</x-badge></span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    {{ $lead->company_name ?? '—' }}
                                    @if ($lead->email)<span class="block text-xs">{{ $lead->email }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $lead->source ? ucfirst($lead->source) : '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$lead->status" /></td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200">
                                            <span class="block h-full rounded-full {{ $lead->score >= 70 ? 'bg-green-500' : ($lead->score >= 40 ? 'bg-yellow-500' : 'bg-slate-400') }}" style="width: {{ $lead->score }}%"></span>
                                        </span>
                                        <span class="text-xs text-slate-600">{{ $lead->score }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $lead->estimated_value !== null ? number_format($lead->estimated_value, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $lead->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('leads.show', $lead) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $lead) @if (! $lead->isConverted()) · <a href="{{ route('leads.edit', $lead) }}" class="text-slate-600 hover:underline">Editar</a>@endif @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $leads->links() }}</div>
        @endif
    </x-card>
@endsection
