@extends('layouts.app', ['header' => 'Centro de Notificaciones', 'subheader' => 'Avisos y asignaciones operativas'])

@section('title', 'Notificaciones')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Notificaciones']]" />
@endsection

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    @if (session('status'))
        <x-flash type="success">{{ session('status') }}</x-flash>
    @endif

    {{-- Barra superior de filtros y acciones --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <div class="inline-flex rounded-lg bg-slate-100 p-1 text-xs font-semibold">
            <a href="{{ route('notifications.index', ['filter' => 'all']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $filter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                Todas
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $filter === 'unread' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                No leídas @if($unreadCount > 0)<span class="ml-1 rounded-full bg-cyan-600 px-1.5 py-0.2 text-[10px] text-white">{{ $unreadCount }}</span>@endif
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'read']) }}"
               class="rounded-md px-3 py-1.5 transition-colors {{ $filter === 'read' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                Leídas
            </a>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 transition-colors">
                    ✓ Marcar todas como leídas
                </button>
            </form>
        @endif
    </div>

    {{-- Listado de notificaciones --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs divide-y divide-slate-100">
        @forelse ($notifications as $notification)
            @php
                $data = $notification->computed_data ?? $notification->data;
                $isUnread = $notification->read_at === null;
                $hasAccess = $data['has_access'] ?? false;
            @endphp
            <div class="p-5 flex items-start justify-between gap-4 transition-colors {{ $isUnread ? 'bg-cyan-50/20' : 'bg-white' }}">
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <div class="mt-1">
                        @if ($isUnread)
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-cyan-600 ring-4 ring-cyan-100" title="No leída"></span>
                        @else
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-300" title="Leída"></span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $data['title'] ?? 'Aviso' }}</h4>
                            <span class="text-xs text-slate-400 font-mono">• {{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $data['message'] ?? '' }}</p>

                        <div class="mt-3 flex items-center gap-3">
                            @if ($hasAccess && !empty($data['url']))
                                <form method="POST" action="{{ route('notifications.read', ['notification' => $notification->id, 'navigate' => 1]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center text-xs font-semibold text-cyan-700 hover:text-cyan-800 underline">
                                        Ir al recurso →
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400 italic">
                                    [Entidad archivada o fuera de alcance]
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($isUnread)
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="rounded-lg p-1.5 text-xs text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" title="Marcar como leída">
                            Marcar leída
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <div class="p-12 text-center text-slate-500">
                <p class="text-sm">No tienes notificaciones en esta bandeja.</p>
            </div>
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
