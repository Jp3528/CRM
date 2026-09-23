@extends('layouts.app', ['header' => $task->title, 'subheader' => 'Detalle de tarea'])

@section('title', $task->title)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tareas', 'url' => route('tasks.index')], ['label' => $task->title]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$task->status" />
        <x-badge color="{{ $task->priority === 'urgent' ? 'red' : ($task->priority === 'high' ? 'yellow' : 'slate') }}">{{ ucfirst($task->priority) }}</x-badge>
        @if ($task->is_overdue)<x-badge color="red">Vencida</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)
                <a href="{{ route('tasks.edit', $task) }}" class="text-slate-700 hover:underline">Editar</a>
                @if (in_array($task->status, ['pending', 'in_progress'], true))
                    <form method="POST" action="{{ route('tasks.complete', $task) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="font-medium text-green-700 hover:underline">Completar</button>
                    </form>
                    <form method="POST" action="{{ route('tasks.cancel', $task) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-slate-500 hover:underline">Cancelar</button>
                    </form>
                @endif
                @if (in_array($task->status, ['completed', 'cancelled'], true))
                    <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="inline">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-slate-700 hover:underline">Reabrir</button>
                    </form>
                @endif
            @endif
            @can('delete', $task)
                <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar esta tarea?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <x-card title="Datos de la tarea">
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div class="col-span-2"><dt class="text-slate-500">Título</dt><dd class="font-medium">{{ $task->title }}</dd></div>
            <div><dt class="text-slate-500">Asignado a</dt><dd class="font-medium">{{ $task->assignee?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Creada por</dt><dd class="font-medium">{{ $task->creator?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Vencimiento</dt><dd class="font-medium">{{ $task->due_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Completada</dt><dd class="font-medium">{{ $task->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Relacionado con</dt><dd class="font-medium">@if ($task->related_url && $canViewRelated)<a href="{{ $task->related_url }}" class="hover:underline">{{ $task->related_label }}</a>@else {{ $canViewRelated ? $task->related_label : '—' }}@endif</dd></div>
        </dl>
        @if ($task->description)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción</p><p class="mt-1 whitespace-pre-line">{{ $task->description }}</p></div>
        @endif
        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
            Creada {{ $task->created_at->format('Y-m-d H:i') }} · Actualizada {{ $task->updated_at->format('Y-m-d H:i') }}
        </div>
    </x-card>
@endsection
