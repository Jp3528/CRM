@extends('layouts.app', ['header' => 'Kanban — '.$pipeline->name, 'subheader' => 'Arrastra tarjetas entre etapas'])

@section('title', 'Kanban')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Oportunidades', 'url' => route('opportunities.index')], ['label' => 'Kanban']]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ route('opportunities.kanban') }}" class="flex items-center gap-2 text-sm">
            <x-label for="pipeline_id" value="Pipeline" />
            <select id="pipeline_id" name="pipeline_id" onchange="this.form.submit()" class="rounded-md border-slate-300 px-3 py-1.5 text-sm">
                @foreach ($pipelines as $p)<option value="{{ $p->id }}" @selected($p->id === $pipeline->id)>{{ $p->name }}</option>@endforeach
            </select>
        </form>
        <a href="{{ route('opportunities.index') }}" class="ml-auto text-sm text-slate-700 hover:underline">← Volver al listado</a>
    </div>

    <div id="kanban-error" class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>

    <div class="flex gap-3 overflow-x-auto pb-4" x-data="kanban({{ $canMove ? 'true' : 'false' }})">
        @foreach ($stages as $stage)
            <div class="w-72 shrink-0 rounded-lg border border-slate-200 bg-slate-50"
                data-stage-id="{{ $stage->id }}"
                data-stage-name="{{ $stage->name }}"
                @dragover.prevent="$el.classList.add('ring-2', 'ring-slate-400')"
                @dragleave="$el.classList.remove('ring-2', 'ring-slate-400')"
                @drop.prevent="$el.classList.remove('ring-2', 'ring-slate-400'); dropCard($event, {{ $stage->id }})">
                <div class="border-b border-slate-200 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800">{{ $stage->name }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $totals[$stage->id]['count'] }} · {{ number_format($totals[$stage->id]['amount'], 2) }}
                    </p>
                </div>
                <div class="max-h-[60vh] space-y-2 overflow-y-auto p-2" data-cards>
                    @foreach ($stage->opportunities as $opp)
                        <article class="cursor-move rounded-md border border-slate-200 bg-white p-3 shadow-sm"
                            draggable="{{ $canMove ? 'true' : 'false' }}"
                            data-id="{{ $opp->id }}"
                            @dragstart="dragCard($event, {{ $opp->id }})">
                            <p class="text-sm font-medium text-slate-900">
                                <a href="{{ route('opportunities.show', $opp) }}" class="hover:underline" @click.stop>{{ $opp->name }}</a>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $opp->company?->trade_name ?? '—' }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ $opp->amount !== null ? number_format($opp->amount, 2).' '.$opp->currency : '—' }}
                                · {{ $opp->probability ?? '—' }}%
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $opp->owner?->name ?? '—' }} · cierra {{ $opp->expected_close_date?->format('Y-m-d') ?? '—' }}
                            </p>

                            @if ($canMove)
                                <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between" @click.stop>
                                    <label for="stage-select-{{ $opp->id }}" class="text-[10px] uppercase font-semibold text-slate-400">Etapa:</label>
                                    <select id="stage-select-{{ $opp->id }}"
                                        @change="moveCardViaSelect({{ $opp->id }}, $event.target.value, {{ $stage->id }})"
                                        class="rounded text-[11px] py-0.5 px-1.5 border border-slate-200 bg-slate-50 text-slate-700 focus:ring-1 focus:ring-cyan-500">
                                        @foreach ($stages as $s)
                                            <option value="{{ $s->id }}" @selected($s->id === $stage->id)>{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </article>
                    @endforeach
                    @if ($stage->opportunities->isEmpty())
                        <p class="px-1 py-3 text-center text-xs text-slate-400">Sin oportunidades.</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <script>
    function kanban(canMove) {
        return {
            canMove,
            draggedId: null,
            dragCard(event, id) {
                if (!this.canMove) {
                    event.preventDefault();
                    return;
                }
                this.draggedId = id;
                this.origin = event.target.closest('[data-cards]');
                event.dataTransfer.effectAllowed = 'move';
            },
            async dropCard(event, stageId) {
                const id = this.draggedId;
                if (!id) return;
                const card = document.querySelector(`[data-id="${id}"]`);
                const origin = card?.closest('[data-cards]');
                const target = event.currentTarget.querySelector('[data-cards]');
                if (!card || !target || origin === target) return;

                // Optimistic UI: mover visualmente y revertir si el backend falla.
                target.prepend(card);
                hideKanbanError();

                try {
                    const response = await fetch(`/opportunities/${id}/stage`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ pipeline_stage_id: stageId }),
                    });
                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.message || 'No se pudo mover la oportunidad.');
                    }
                    const sel = card.querySelector(`select`);
                    if (sel) sel.value = stageId;
                } catch (e) {
                    origin.prepend(card);
                    showKanbanError(e.message);
                } finally {
                    this.draggedId = null;
                }
            },
            async moveCardViaSelect(id, targetStageId, currentStageId) {
                if (targetStageId == currentStageId) return;
                const card = document.querySelector(`[data-id="${id}"]`);
                const origin = card?.closest('[data-cards]');
                const targetColumn = document.querySelector(`[data-stage-id="${targetStageId}"]`);
                const target = targetColumn?.querySelector('[data-cards]');
                if (!card || !target) return;

                target.prepend(card);
                hideKanbanError();

                try {
                    const response = await fetch(`/opportunities/${id}/stage`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ pipeline_stage_id: targetStageId }),
                    });
                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.message || 'No se pudo mover la oportunidad.');
                    }
                } catch (e) {
                    origin.prepend(card);
                    const sel = card.querySelector(`select`);
                    if (sel) sel.value = currentStageId;
                    showKanbanError(e.message);
                }
            },
        };
    }
    function showKanbanError(message) {
        const el = document.getElementById('kanban-error');
        el.textContent = message;
        el.classList.remove('hidden');
    }
    function hideKanbanError() {
        document.getElementById('kanban-error').classList.add('hidden');
    }
    </script>
@endsection
