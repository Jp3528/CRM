@extends('layouts.app', ['header' => 'Calendario', 'subheader' => $cursor->format('F Y')])

@section('title', 'Calendario')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Calendario']]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <div class="flex gap-1">
            @foreach (['month' => 'Mes', 'week' => 'Semana', 'day' => 'Día'] as $v => $l)
                <a href="{{ route('calendar.index', ['view' => $v, 'date' => $cursor->format('Y-m-d')]) }}"
                   class="rounded-md px-3 py-1.5 {{ $mode === $v ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $l }}</a>
            @endforeach
        </div>
        <div class="flex gap-1">
            <a href="{{ route('calendar.index', ['view' => $mode, 'date' => $cursor->copy()->sub($mode === 'month' ? 'month' : ($mode === 'week' ? 'week' : 'day'), 1)->format('Y-m-d')]) }}"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">←</a>
            <a href="{{ route('calendar.index', ['view' => $mode]) }}"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Hoy</a>
            <a href="{{ route('calendar.index', ['view' => $mode, 'date' => $cursor->copy()->add($mode === 'month' ? 'month' : ($mode === 'week' ? 'week' : 'day'), 1)->format('Y-m-d')]) }}"
               class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">→</a>
        </div>
        <span class="ml-auto flex gap-2">
            @if ($canCreateTask)<a href="{{ route('tasks.create') }}" class="text-slate-700 hover:underline">+ Nueva tarea</a>@endif
            @if ($canCreateMeeting)<a href="{{ route('activities.create', ['type' => 'meeting']) }}" class="text-slate-700 hover:underline">+ Nueva reunión</a>@endif
        </span>
    </div>

    @if ($mode === 'day')
        <x-card title="{{ $cursor->format('l, d M Y') }}" subtitle="{{ count($events[$cursor->format('Y-m-d')] ?? []) }} evento(s)">
            @forelse ($events[$cursor->format('Y-m-d')] ?? [] as $event)
                <div class="flex items-center gap-3 border-b border-slate-100 py-2 text-sm last:border-0">
                    <span class="w-12 shrink-0 text-xs text-slate-500">{{ $event['time'] }}</span>
                    <x-badge color="{{ $event['kind'] === 'task' ? 'yellow' : 'blue' }}">{{ $event['kind'] === 'task' ? 'Tarea' : 'Reunión' }}</x-badge>
                    <a href="{{ $event['url'] }}" class="font-medium hover:underline {{ $event['done'] ? 'text-slate-400 line-through' : '' }}">{{ $event['title'] }}</a>
                    <span class="ml-auto text-xs text-slate-400">{{ $event['meta'] }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sin eventos este día.</p>
            @endforelse
        </x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <div class="grid min-w-[720px] grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold uppercase text-slate-500">
                @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<div class="px-2 py-2">{{ $d }}</div>@endforeach
            </div>
            <div class="grid min-w-[720px] grid-cols-7">
                @for ($day = $gridStart->copy(); $day <= $gridEnd; $day->addDay())
                    @php $key = $day->format('Y-m-d'); $dayEvents = $events[$key] ?? []; @endphp
                    <div class="min-h-24 border-b border-r border-slate-100 p-1.5 {{ $day->isToday() ? 'bg-blue-50/50' : '' }} {{ $mode === 'month' && $day->month !== $cursor->month ? 'bg-slate-50 text-slate-400' : '' }}">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('calendar.index', ['view' => 'day', 'date' => $key]) }}"
                               class="text-xs font-medium hover:underline {{ $day->isToday() ? 'text-blue-700' : 'text-slate-600' }}">{{ $day->format('j') }}</a>
                        </div>
                        <div class="mt-1 space-y-1">
                            @foreach (array_slice($dayEvents, 0, 3) as $event)
                                <a href="{{ $event['url'] }}" title="{{ $event['title'] }} — {{ $event['meta'] }}"
                                   class="block truncate rounded px-1.5 py-0.5 text-[11px] {{ $event['kind'] === 'task' ? 'bg-yellow-100 text-yellow-900' : 'bg-blue-100 text-blue-900' }} {{ $event['done'] ? 'opacity-60 line-through' : '' }}">
                                    {{ $event['time'] }} {{ $event['title'] }}
                                </a>
                            @endforeach
                            @if (count($dayEvents) > 3)
                                <a href="{{ route('calendar.index', ['view' => 'day', 'date' => $key]) }}" class="block px-1.5 text-[11px] text-slate-500 hover:underline">+{{ count($dayEvents) - 3 }} más</a>
                            @endif
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @endif
@endsection
