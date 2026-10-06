@extends('layouts.app', ['header' => 'Detalle de Auditoría #' . $log->id, 'subheader' => 'Inspección de traza de auditoría'])

@section('title', 'Detalle de Auditoría #' . $log->id)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Auditoría', 'url' => route('audit.index')],
        ['label' => '#' . $log->id]
    ]" />
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-6">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Registro #{{ $log->id }}</h2>
                <p class="text-xs text-slate-500 font-mono">{{ $log->created_at?->format('d/m/Y H:i:s') }}</p>
            </div>
            <a href="{{ route('audit.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                ← Volver al registro
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm bg-slate-50 p-4 rounded-lg border border-slate-100">
            <div>
                <span class="text-xs font-semibold uppercase text-slate-500 block">Usuario / Actor</span>
                <span class="font-medium text-slate-900">{{ $log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'Sistema / Automatización' }}</span>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-slate-500 block">Acción</span>
                <span class="font-semibold text-cyan-800">{{ $log->action }}</span>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-slate-500 block">Entidad</span>
                <span class="font-mono text-slate-900">{{ $log->entity_type }} #{{ $log->entity_id ?? '—' }}</span>
            </div>
            <div>
                <span class="text-xs font-semibold uppercase text-slate-500 block">IP y Agente</span>
                <span class="font-mono text-xs text-slate-600 block">{{ $log->ip_address ?? '—' }}</span>
                <span class="text-xs text-slate-500 block truncate" title="{{ $log->user_agent }}">{{ $log->user_agent ?? '—' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-rose-700 mb-2">Valores Anteriores</h3>
                <pre class="bg-rose-50/50 border border-rose-100 text-rose-950 p-4 rounded-lg text-xs font-mono whitespace-pre-wrap overflow-x-auto min-h-[140px]">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '(Sin valores previos o creación)' }}</pre>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 mb-2">Valores Nuevos</h3>
                <pre class="bg-emerald-50/50 border border-emerald-100 text-emerald-950 p-4 rounded-lg text-xs font-mono whitespace-pre-wrap overflow-x-auto min-h-[140px]">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '(Sin valores nuevos o eliminación)' }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection
