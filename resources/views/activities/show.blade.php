@extends('layouts.app', ['header' => $activity->subject ?? ucfirst($activity->type), 'subheader' => 'Detalle de actividad'])

@section('title', $activity->subject ?? 'Actividad')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Actividades', 'url' => route('activities.index')], ['label' => $activity->subject ?? ucfirst($activity->type)]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-badge>{{ ucfirst(str_replace('_', ' ', $activity->type)) }}</x-badge>
        <x-status-badge :status="$activity->status" />
        @if ($activity->is_system)<x-badge color="slate">Sistema (trazabilidad)</x-badge>@endif
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('activities.edit', $activity) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @if ($canDelete)
                <form method="POST" action="{{ route('activities.destroy', $activity) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar esta actividad?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endif
        </span>
    </div>

    <x-card title="Datos de la actividad">
        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div class="col-span-2"><dt class="text-slate-500">Título</dt><dd class="font-medium">{{ $activity->subject ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Usuario</dt><dd class="font-medium">{{ $activity->user?->name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Relacionado con</dt><dd class="font-medium">@if ($activity->related_url)<a href="{{ $activity->related_url }}" class="hover:underline">{{ $activity->related_label }}</a>@else {{ $activity->related_label }}@endif</dd></div>
            <div><dt class="text-slate-500">Programada</dt><dd class="font-medium">{{ $activity->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Completada</dt><dd class="font-medium">{{ $activity->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        @if ($activity->description)
            <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción</p><p class="mt-1 whitespace-pre-line">{{ $activity->description }}</p></div>
        @endif
        <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
            Registrada {{ $activity->created_at->format('Y-m-d H:i') }} · Actualizada {{ $activity->updated_at->format('Y-m-d H:i') }}
        </div>
    </x-card>
@endsection
