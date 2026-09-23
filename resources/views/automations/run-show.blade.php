@extends('layouts.app', ['header' => 'Ejecucion', 'subheader' => $automation->name])

@section('title', 'Ejecucion')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones', 'url' => route('automations.index')], ['label' => $automation->name, 'url' => route('automations.show', $automation)], ['label' => 'Ejecucion']]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$run->status" :label="ucfirst($run->status)" />
        <x-badge>{{ $run->trigger_type }}</x-badge>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Resumen">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Trigger</dt><dd class="font-medium">{{ $run->trigger_type }}</dd></div>
                <div><dt class="text-slate-500">Registro</dt><dd class="font-medium">{{ $run->subject_type }} #{{ $run->subject_id }}</dd></div>
                <div><dt class="text-slate-500">Disparada por</dt><dd class="font-medium">{{ $run->triggerer?->name ?? 'Sistema' }}</dd></div>
                <div><dt class="text-slate-500">Duracion</dt><dd class="font-medium">{{ $run->duration_seconds !== null ? number_format($run->duration_seconds, 2).' s' : '—' }}</dd></div>
                <div><dt class="text-slate-500">Inicio</dt><dd class="font-medium">{{ $run->started_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Fin</dt><dd class="font-medium">{{ $run->finished_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Resultado (seguro)">
            @if ($run->error_message)
                <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $run->error_message }}</p>
            @elseif (($run->result['reason'] ?? null))
                <p class="text-sm">Omitida: <strong>{{ $run->result['reason'] }}</strong></p>
            @elseif (! empty($run->result['actions']))
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    @foreach ($run->result['actions'] as $step)
                        <li>{{ $step['action'] ?? 'accion' }}: {{ $step['status'] ?? '' }}
                            @if (isset($step['task_id'])) (tarea #{{ $step['task_id'] }})@endif
                            @if (isset($step['activity_id'])) (actividad #{{ $step['activity_id'] }})@endif
                            @if (isset($step['campaign_member_id'])) (miembro #{{ $step['campaign_member_id'] }})@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-500">Sin detalle.</p>
            @endif
        </x-card>
    </div>
@endsection
