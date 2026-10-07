@extends('layouts.app', ['header' => 'Automatizaciones', 'subheader' => 'Motor interno event-driven (sin codigo externo)'])

@section('title', 'Automatizaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones']]" />
@endsection

@section('content')
    <x-card title="Automatizaciones" subtitle="{{ $automations->total() }} registro(s)">
        <form method="GET" action="{{ route('automations.index') }}" class="mb-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre o descripcion…"
                class="w-full min-w-0 rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 sm:col-span-2 md:col-span-2 lg:col-span-4">
            <select name="status" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-2">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="trigger_type" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-3">
                <option value="">Todos los triggers</option>
                @foreach ($triggers as $t => $meta)<option value="{{ $t }}" @selected($filters['trigger_type'] === $t)>{{ $meta['label'] }}</option>@endforeach
            </select>
            <select name="owner_id" class="w-full min-w-0 rounded-md border-slate-300 px-2 py-2 text-sm sm:col-span-1 md:col-span-1 lg:col-span-3">
                <option value="">Todos los responsables</option>
                @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) $filters['owner_id'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <div class="col-span-full flex flex-wrap items-center gap-2 pt-1">
                <x-button>Buscar</x-button>
                <a href="{{ route('automations.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Automation::class)
                    <a href="{{ route('automations.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva automatizacion</a>
                @endcan
            </div>
        </form>

        @if ($automations->isEmpty())
            <x-empty-state title="No hay automatizaciones." message="Crea la primera regla interna (borrador, sin ejecucion)."
                :action-url="auth()->user()->can('create', App\Models\Automation::class) ? route('automations.create') : null" action-label="Nueva automatizacion" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-left">Trigger</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-right">Cond/Acc</th>
                            <th class="px-4 py-2 text-left">Ultima ejecucion</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($automations as $automation)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2"><a href="{{ route('automations.show', $automation) }}" class="font-medium hover:underline">{{ $automation->name }}</a></td>
                                <td class="px-4 py-2"><x-status-badge :status="$automation->status" :label="ucfirst($automation->status)" /></td>
                                <td class="px-4 py-2 text-slate-600">{{ $triggers[$automation->trigger_type]['label'] ?? $automation->trigger_type }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $automation->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ count($automation->conditions ?? []) }}/{{ count($automation->actions ?? []) }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $automation->last_run_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('automations.show', $automation) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $automation)
                                        @if ($automation->isEditable())
                                            · <a href="{{ route('automations.edit', $automation) }}" class="text-slate-600 hover:underline">Editar</a>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $automations->links() }}</div>
        @endif
    </x-card>
@endsection
