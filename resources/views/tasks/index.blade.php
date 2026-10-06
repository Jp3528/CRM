@extends('layouts.app', ['header' => 'Tareas', 'subheader' => 'Seguimiento comercial'])

@section('title', 'Tareas')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tareas']]" />
@endsection

@section('content')
    <x-card title="Tareas" subtitle="{{ $tasks->total() }} registro(s)">
        <form method="GET" action="{{ route('tasks.index') }}" class="mb-4 grid gap-2 md:grid-cols-6">
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Buscar título o descripción…"
                class="rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 md:col-span-2">
            <select name="status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
            </select>
            <select name="priority" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todas las prioridades</option>
                @foreach ($priorities as $p)<option value="{{ $p }}" @selected($filters['priority'] === $p)>{{ ucfirst($p) }}</option>@endforeach
            </select>
            <select name="assigned_to" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos los asignados</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) $filters['assigned_to'] === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <select name="related_type" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Toda entidad</option>
                <option value="none" @selected($filters['related_type'] === 'none')>Sin entidad</option>
                @foreach ($relatedTypes as $t)<option value="{{ $t }}" @selected($filters['related_type'] === $t)>{{ ucfirst($t) }}</option>@endforeach
            </select>
            <select name="due" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                @foreach (['all' => 'Todos los vencimientos', 'today' => 'Hoy', 'overdue' => 'Vencidas', 'upcoming' => 'Próximas', 'completed' => 'Completadas'] as $v => $l)<option value="{{ $v }}" @selected($filters['due'] === $v)>{{ $l }}</option>@endforeach
            </select>
            <div class="flex gap-2 md:col-span-6">
                <x-button>Buscar</x-button>
                <a href="{{ route('tasks.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
                @if (auth()->user()?->hasPermission('exports.view'))
                    <a href="{{ route('exports.module', array_merge(['module' => 'tasks'], request()->query())) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" title="Descargar registros filtrados en CSV compatible con Excel">
                        <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar CSV
                    </a>
                @endif
                @can('create', App\Models\Task::class)
                    <a href="{{ route('tasks.create') }}" class="ml-auto inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Nueva tarea</a>
                @endcan
            </div>
        </form>

        @if ($tasks->isEmpty())
            <x-empty-state title="No hay tareas." message="Crea la primera tarea de seguimiento comercial."
                :action-url="auth()->user()->can('create', App\Models\Task::class) ? route('tasks.create') : null" action-label="Nueva tarea" />
        @else
            <div class="overflow-x-auto rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tasks.index', array_merge(request()->query(), ['sort' => 'title', 'direction' => $filters['sort'] === 'title' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Título{{ $filters['sort'] === 'title' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">Relacionado con</th>
                            <th class="px-4 py-2 text-left">Asignado</th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tasks.index', array_merge(request()->query(), ['sort' => 'status', 'direction' => $filters['sort'] === 'status' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Estado{{ $filters['sort'] === 'status' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tasks.index', array_merge(request()->query(), ['sort' => 'priority', 'direction' => $filters['sort'] === 'priority' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Prioridad{{ $filters['sort'] === 'priority' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-left">
                                <a href="{{ route('tasks.index', array_merge(request()->query(), ['sort' => 'due_at', 'direction' => $filters['sort'] === 'due_at' && $filters['direction'] === 'asc' ? 'desc' : 'asc'])) }}" class="hover:text-slate-800">Vence{{ $filters['sort'] === 'due_at' ? ($filters['direction'] === 'asc' ? ' ↑' : ' ↓') : '' }}</a>
                            </th>
                            <th class="px-4 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($tasks as $task)
                            <tr class="hover:bg-slate-50 {{ $task->is_overdue ? 'bg-red-50/50' : '' }}">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    <a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                                    @if ($task->is_overdue)<span class="ml-1"><x-badge color="red">Vencida</x-badge></span>@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">
                                    @if ($task->related_url)<a href="{{ $task->related_url }}" class="hover:underline">{{ $task->related_label }}</a>@else {{ $task->related_label }}@endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $task->assignee?->name ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$task->status" /></td>
                                <td class="px-4 py-2"><x-badge color="{{ $task->priority === 'urgent' ? 'red' : ($task->priority === 'high' ? 'yellow' : 'slate') }}">{{ ucfirst($task->priority) }}</x-badge></td>
                                <td class="px-4 py-2 {{ $task->is_overdue ? 'font-medium text-red-700' : 'text-slate-600' }}">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('tasks.show', $task) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', $task) · <a href="{{ route('tasks.edit', $task) }}" class="text-slate-600 hover:underline">Editar</a>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $tasks->links() }}</div>
        @endif
    </x-card>
@endsection
