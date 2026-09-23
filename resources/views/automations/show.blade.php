@extends('layouts.app', ['header' => $automation->name, 'subheader' => $triggerLabel])

@section('title', $automation->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones', 'url' => route('automations.index')], ['label' => $automation->name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$automation->status" :label="ucfirst($automation->status)" />
        <x-badge>{{ $automation->trigger_type }}</x-badge>
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate && $automation->isEditable())<a href="{{ route('automations.edit', $automation) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('delete', $automation)
                @if ($automation->status !== 'active')
                    <form method="POST" action="{{ route('automations.destroy', $automation) }}" class="inline"
                        x-data @submit.prevent="if (confirm('Eliminar automatizacion? El historial se conserva.')) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                @endif
            @endcan
        </span>
    </div>

    @if ($canExecute)
        <x-card title="Estado" subtitle="Solo por activar/pausar; el estado no se edita a mano">
            <div class="flex flex-wrap items-center gap-2">
                @if (in_array($automation->status, ['draft', 'paused'], true))
                    <form method="POST" action="{{ route('automations.activate', $automation) }}" class="inline">
                        @csrf
                        <x-button>Activar</x-button>
                    </form>
                @endif
                @if ($automation->status === 'active')
                    <form method="POST" action="{{ route('automations.pause', $automation) }}" class="inline">
                        @csrf
                        <x-button variant="secondary">Pausar</x-button>
                    </form>
                @endif
            </div>
            @if ($errors->any())
                <ul class="mt-2 text-sm text-red-600">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Probar automatizacion (dry run)" subtitle="Sin modificar datos: solo evalua y muestra el plan">
            <form method="POST" action="{{ route('automations.dry-run', $automation) }}" class="grid gap-2 md:grid-cols-4">
                @csrf
                <select name="subject_type" required class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="lead">lead</option>
                    <option value="contact">contact</option>
                    <option value="opportunity">opportunity</option>
                    <option value="task">task</option>
                    <option value="ticket">ticket</option>
                    <option value="quote">quote</option>
                    <option value="sale">sale</option>
                    <option value="invoice">invoice</option>
                    <option value="campaign">campaign</option>
                </select>
                <input type="number" name="subject_id" required min="1" placeholder="ID visible en tu alcance"
                    class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <div class="md:col-span-2"><x-button variant="secondary">Probar</x-button></div>
            </form>
        </x-card>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Definicion">
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-slate-500">Trigger</dt><dd class="font-medium">{{ $triggerLabel }} ({{ $automation->trigger_type }})</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $automation->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Condiciones (AND)</dt>
                    <dd class="font-medium">
                        @if (empty($automation->conditions)) Sin condiciones: ejecuta siempre.
                        @else
                            <ul class="list-disc pl-5">
                                @foreach ($automation->conditions as $c)<li>{{ $c['field'] }} {{ $c['operator'] }} {{ is_array($c['value'] ?? null) ? implode(', ', $c['value']) : ($c['value'] ?? 'null') }}</li>@endforeach
                            </ul>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-slate-500">Acciones</dt>
                    <dd class="font-medium">
                        <ol class="list-decimal pl-5">
                            @foreach ($automation->actions ?? [] as $a)<li>{{ $a['type'] }}</li>@endforeach
                        </ol>
                    </dd>
                </div>
            </dl>
            @if ($automation->description)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Descripcion</p><p class="mt-1 whitespace-pre-line">{{ $automation->description }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $automation->created_at->format('Y-m-d H:i') }} · Ultima ejecucion {{ $automation->last_run_at?->format('Y-m-d H:i') ?? '—' }}
            </div>
        </x-card>

        <x-card title="Ejecuciones recientes" subtitle="Historial auditable (sin stack traces)">
            @if ($runs->isEmpty())
                <p class="text-sm text-slate-500">No hay ejecuciones registradas.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($runs as $run)
                        <li class="flex items-center gap-2 py-2">
                            <a href="{{ route('automations.runs.show', [$automation, $run]) }}" class="font-medium hover:underline">{{ $run->created_at->format('Y-m-d H:i') }}</a>
                            <x-status-badge :status="$run->status" :label="ucfirst($run->status)" />
                            <span class="text-xs text-slate-400">{{ $run->subject_type }} #{{ $run->subject_id }}</span>
                            <span class="ml-auto text-xs text-slate-400">{{ $run->triggerer?->name ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4">{{ $runs->links() }}</div>
            @endif
        </x-card>
    </div>
@endsection
