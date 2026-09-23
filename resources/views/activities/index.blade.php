@extends('layouts.app', ['header' => 'Actividades', 'subheader' => 'Timeline comercial'])

@section('title', 'Actividades')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Actividades']]" />
@endsection

@section('content')
    <x-card title="Actividades" subtitle="{{ $activities->total() }} registro(s)">
        <form method="GET" action="{{ route('activities.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar título o descripción…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="type" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los tipos</option>
                @foreach ($types as $t)<option value="{{ $t }}" @selected($filters['type'] === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}{{ in_array($t, \App\Models\Activity::SYSTEM_TYPES, true) ? ' (sistema)' : '' }}</option>@endforeach
            </select>
            <select name="user_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los usuarios</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) $filters['user_id'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <select name="related_type" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Toda entidad</option>
                <option value="none" @selected($filters['related_type'] === 'none')>Sin entidad</option>
                @foreach ($relatedTypes as $t)<option value="{{ $t }}" @selected($filters['related_type'] === $t)>{{ ucfirst($t) }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <input type="date" name="from" value="{{ $filters['from'] }}"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
                <input type="date" name="to" value="{{ $filters['to'] }}"
                    class="w-full rounded-md border-slate-300 px-2 py-2 text-sm">
            </div>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('activities.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @can('create', App\Models\Activity::class)
                    <a href="{{ route('activities.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Registrar actividad</a>
                @endcan
            </div>
        </form>

        @if ($activities->isEmpty())
            <x-empty-state title="No hay actividades." message="Registra llamadas, emails, reuniones o notas."
                :action-url="auth()->user()->can('create', App\Models\Activity::class) ? route('activities.create') : null" action-label="Registrar actividad" />
        @else
            <ol class="relative space-y-3 border-l border-slate-200 pl-4">
                @foreach ($activities as $activity)
                    <li class="text-sm">
                        <p class="font-medium">
                            <a href="{{ route('activities.show', $activity) }}" class="hover:underline">{{ $activity->subject ?? ucfirst($activity->type) }}</a>
                            @if ($activity->is_system)<span class="ml-1"><x-badge color="slate">Sistema</x-badge></span>@endif
                        </p>
                        @if ($activity->description)<p class="text-slate-600">{{ \Illuminate\Support\Str::limit($activity->description, 140) }}</p>@endif
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ ucfirst(str_replace('_', ' ', $activity->type)) }} · {{ $activity->user?->name ?? '—' }}
                            @if ($activity->related_label !== '—') · {{ $activity->related_label }}@endif
                            · {{ ($activity->scheduled_at ?? $activity->created_at)->format('Y-m-d H:i') }}
                        </p>
                    </li>
                @endforeach
            </ol>
            <div class="mt-4">{{ $activities->links() }}</div>
        @endif
    </x-card>
@endsection
