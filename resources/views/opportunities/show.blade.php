@extends('layouts.app', ['header' => $opportunity->name, 'subheader' => 'Ficha de oportunidad'])

@section('title', $opportunity->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Oportunidades', 'url' => route('opportunities.index')], ['label' => $opportunity->name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$opportunity->status" />
        <x-badge>{{ $opportunity->stage?->name ?? '—' }}</x-badge>
        @foreach ($opportunity->tags as $tag)
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                {{ $tag->name }}
                @if ($canUpdate)
                    <form method="POST" action="{{ route('opportunities.tags.detach', [$opportunity, $tag]) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Quitar">×</button>
                    </form>
                @endif
            </span>
        @endforeach
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('opportunities.edit', $opportunity) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('delete', $opportunity)
                <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $opportunity->name }}? No afecta empresa/contacto/lead.')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos comerciales">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Monto</dt><dd class="font-medium">{{ $opportunity->amount !== null ? number_format($opportunity->amount, 2).' '.$opportunity->currency : '—' }}</dd></div>
                <div><dt class="text-slate-500">Probabilidad</dt><dd class="font-medium">{{ $opportunity->probability !== null ? $opportunity->probability.'%' : '—' }}</dd></div>
                <div><dt class="text-slate-500">Valor ponderado</dt><dd class="font-medium">{{ $opportunity->weighted_amount !== null ? number_format($opportunity->weighted_amount, 2) : '—' }}</dd></div>
                <div><dt class="text-slate-500">Pipeline</dt><dd class="font-medium">{{ $opportunity->pipeline?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Etapa</dt><dd class="font-medium">{{ $opportunity->stage?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Cierre previsto</dt><dd class="font-medium">{{ $opportunity->expected_close_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Cierre real</dt><dd class="font-medium">{{ $opportunity->actual_close_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $opportunity->owner?->name ?? '—' }}</dd></div>
            </dl>
            @if ($opportunity->status === 'lost' && $opportunity->loss_reason)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Motivo de pérdida</p><p class="mt-1">{{ $opportunity->loss_reason }}</p></div>
            @endif
            @if ($opportunity->description)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripción</p><p class="mt-1 whitespace-pre-line">{{ $opportunity->description }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $opportunity->created_at->format('Y-m-d H:i') }} · Actualizada {{ $opportunity->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>

        <x-card title="Relaciones">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($opportunity->company)<a href="{{ route('companies.show', $opportunity->company) }}" class="hover:underline">{{ $opportunity->company->trade_name }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Contacto</dt><dd class="font-medium">@if ($opportunity->contact)<a href="{{ route('contacts.show', $opportunity->contact) }}" class="hover:underline">{{ $opportunity->contact->first_name }} {{ $opportunity->contact->last_name }}</a>@else — @endif</dd></div>
                <div><dt class="text-slate-500">Lead origen</dt><dd class="font-medium">@if ($opportunity->lead)<a href="{{ route('leads.show', $opportunity->lead) }}" class="hover:underline">{{ $opportunity->lead->first_name }} {{ $opportunity->lead->last_name }}</a>@else — @endif</dd></div>
            </dl>
        </x-card>
    </div>

    @if ($canMove)
        <x-card title="Mover de etapa" subtitle="Pasa siempre por el backend (historial + sincronización)">
            <form method="POST" action="{{ route('opportunities.stage.update', $opportunity) }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                @method('PATCH')
                <div>
                    <x-label for="pipeline_stage_id" value="Etapa destino *" />
                    <select id="pipeline_stage_id" name="pipeline_stage_id" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        @foreach ($pipelineStages as $s)<option value="{{ $s->id }}" @selected($s->id === $opportunity->pipeline_stage_id)>{{ $s->name }}{{ $s->is_won ? ' (ganada)' : '' }}{{ $s->is_lost ? ' (perdida)' : '' }}</option>@endforeach
                    </select>
                    <x-input-error :message="$errors->get('pipeline_stage_id')[0] ?? null" />
                </div>
                <div>
                    <x-label for="loss_reason" value="Motivo (requerido si Perdida)" />
                    <input id="loss_reason" name="loss_reason" type="text" value="{{ old('loss_reason') }}"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <x-input-error :message="$errors->get('loss_reason')[0] ?? null" />
                </div>
                <div>
                    <x-label for="notes" value="Notas del movimiento" />
                    <input id="notes" name="notes" type="text" value="{{ old('notes') }}"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-end"><x-button>Mover</x-button></div>
            </form>
        </x-card>
    @endif

    <x-card title="Historial de etapas" subtitle="{{ $opportunity->stageHistory->count() }} movimiento(s)">
        @if ($opportunity->stageHistory->isEmpty())
            <p class="text-sm text-slate-500">Sin movimientos registrados.</p>
        @else
            <ol class="relative space-y-3 border-l border-slate-200 pl-4 text-sm">
                @foreach ($opportunity->stageHistory as $h)
                    <li>
                        <p class="font-medium">{{ $h->fromStage?->name ?? 'Creación' }} → {{ $h->toStage?->name ?? '—' }}</p>
                        @if ($h->notes)<p class="text-slate-600">{{ $h->notes }}</p>@endif
                        <p class="text-xs text-slate-400">{{ $h->changedBy?->name ?? '—' }} · {{ $h->changed_at->format('Y-m-d H:i') }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Actividad reciente">
            @if ($opportunity->activities->isEmpty())
                <p class="text-sm text-slate-500">Sin actividad registrada.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($opportunity->activities as $activity)
                        <li class="py-2">
                            <p class="font-medium">{{ $activity->subject ?? $activity->type }}</p>
                            @if ($activity->description)<p class="text-slate-600">{{ $activity->description }}</p>@endif
                            <p class="mt-0.5 text-xs text-slate-400">{{ $activity->type }} · {{ $activity->user?->name ?? '—' }} · {{ $activity->created_at->format('Y-m-d H:i') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Tareas relacionadas" subtitle="Solo lectura en esta fase">
            @if ($opportunity->tasks->isEmpty())
                <p class="text-sm text-slate-500">Sin tareas asociadas.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($opportunity->tasks as $task)
                        <li class="py-2">
                            <p class="font-medium">{{ $task->title }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $task->status }} · {{ $task->priority ?? 'sin prioridad' }} · vence {{ $task->due_at?->format('Y-m-d') ?? '—' }} · {{ $task->assignee?->name ?? 'sin asignar' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
