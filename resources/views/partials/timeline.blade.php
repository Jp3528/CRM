@php
$items = collect();

foreach (($subject->activities ?? []) as $activity) {
    $items->push([
        'date' => $activity->scheduled_at ?? $activity->created_at,
        'kind' => 'activity',
        'badge' => ucfirst(str_replace('_', ' ', $activity->type)),
        'title' => $activity->subject ?? ucfirst(str_replace('_', ' ', $activity->type)),
        'user' => $activity->user?->name ?? '—',
        'body' => $activity->description,
        'system' => $activity->is_system,
    ]);
}

foreach (($subject->tasks ?? []) as $task) {
    $items->push([
        'date' => $task->created_at,
        'kind' => 'task',
        'badge' => 'Tarea · '.ucfirst(str_replace('_', ' ', $task->status)),
        'title' => $task->title,
        'user' => $task->assignee?->name ?? $task->creator?->name ?? '—',
        'body' => $task->description,
        'system' => false,
        'url' => route('tasks.show', $task),
    ]);
}

if (isset($subject->stageHistory)) {
    foreach ($subject->stageHistory as $h) {
        $items->push([
            'date' => $h->changed_at,
            'kind' => 'stage',
            'badge' => 'Etapa',
            'title' => ($h->fromStage?->name ?? 'Creación').' → '.($h->toStage?->name ?? '—'),
            'user' => $h->changedBy?->name ?? '—',
            'body' => $h->notes,
            'system' => true,
        ]);
    }
}

$items = $items->sortByDesc('date')->values();
@endphp

<x-card title="Timeline" subtitle="Actividades, tareas y cambios, lo más reciente primero">
    @if ($items->isEmpty())
        <p class="text-sm text-slate-500">Sin movimientos registrados.</p>
    @else
        <ol class="relative space-y-3 border-l border-slate-200 pl-4">
            @foreach ($items as $item)
                <li class="text-sm">
                    <p class="font-medium">
                        @if (! empty($item['url'] ?? null))
                            <a href="{{ $item['url'] }}" class="hover:underline">{{ $item['title'] }}</a>
                        @else
                            {{ $item['title'] }}
                        @endif
                        <span class="ml-1 text-xs font-normal text-slate-400">{{ $item['badge'] }}</span>
                    </p>
                    @if ($item['body'])<p class="text-slate-600">{{ \Illuminate\Support\Str::limit($item['body'], 160) }}</p>@endif
                    <p class="mt-0.5 text-xs text-slate-400">{{ $item['user'] }} · {{ $item['date']?->format('Y-m-d H:i') ?? '—' }}</p>
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
