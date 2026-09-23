@extends('layouts.app', ['header' => 'Plantillas', 'subheader' => 'Mensajes reutilizables (texto plano)'])

@section('title', 'Plantillas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Plantillas']]" />
@endsection

@section('content')
    <x-card title="Plantillas de mensajes" subtitle="{{ $templates->total() }} registro(s)">
        <form method="GET" action="{{ route('templates.index') }}" class="mb-4 grid gap-2 md:grid-cols-5">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar nombre, asunto o cuerpo…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm md:col-span-2">
            <select name="channel" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los canales</option>
                @foreach ($channels as $c)<option value="{{ $c }}" @selected($filters['channel'] === $c)>{{ ucfirst($c) }}</option>@endforeach
            </select>
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-5">
                <x-button>Buscar</x-button>
                <a href="{{ route('templates.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\MessageTemplate::class)
                    <a href="{{ route('templates.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva plantilla</a>
                @endcan
            </div>
        </form>

        @if ($templates->isEmpty())
            <x-empty-state title="No hay plantillas." message="Crea la primera plantilla de mensaje."
                :action-url="auth()->user()->can('create', App\Models\MessageTemplate::class) ? route('templates.create') : null" action-label="Nueva plantilla" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Nombre</th>
                            <th class="px-4 py-2 text-left">Canal</th>
                            <th class="px-4 py-2 text-left">Estado</th>
                            <th class="px-4 py-2 text-left">Responsable</th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($templates as $template)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2"><a href="{{ route('templates.show', $template) }}" class="font-medium hover:underline">{{ $template->name }}</a></td>
                                <td class="px-4 py-2"><x-badge>{{ ucfirst($template->channel) }}</x-badge></td>
                                <td class="px-4 py-2"><x-status-badge :status="$template->status" :label="ucfirst($template->status)" /></td>
                                <td class="px-4 py-2 text-slate-600">{{ $template->owner?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('templates.show', $template) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $template) · <a href="{{ route('templates.edit', $template) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $templates->links() }}</div>
        @endif
    </x-card>
@endsection
