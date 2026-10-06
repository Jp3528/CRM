@extends('layouts.app', ['header' => "Pipeline: {$pipeline->name}", 'subheader' => 'Etapas de venta, probabilidad y reglas de cierre'])

@section('title', "Pipeline: {$pipeline->name}")

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Pipelines', 'url' => route('settings.pipelines.index')],
        ['label' => $pipeline->name],
    ]" />
@endsection

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <x-flash type="success">{{ session('status') }}</x-flash>
        @endif

        @if ($errors->any())
            <x-flash type="danger">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </x-flash>
        @endif

        {{-- Cabecera con datos del pipeline --}}
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-3">
                        <h2 class="text-lg font-bold text-slate-900">{{ $pipeline->name }}</h2>
                        <x-badge :color="$pipeline->status === 'active' ? 'emerald' : 'slate'">
                            {{ $pipeline->status === 'active' ? 'Activo' : 'Inactivo' }}
                        </x-badge>
                        @if ($pipeline->is_default)
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Predeterminado</span>
                        @endif
                    </div>
                    @if ($pipeline->description)
                        <p class="text-xs text-slate-500 mt-1">{{ $pipeline->description }}</p>
                    @endif
                </div>

                <div class="flex items-center space-x-3">
                    <a href="{{ route('settings.pipelines.edit', $pipeline) }}" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        Editar pipeline
                    </a>
                </div>
            </div>
        </x-card>

        {{-- Listado de etapas del pipeline --}}
        <x-card title="Etapas comerciales del pipeline" subtitle="El cambio de probabilidad sincroniza automáticamente las oportunidades abiertas.">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-center w-16">Posición</th>
                            <th class="px-6 py-3">Nombre de la etapa</th>
                            <th class="px-4 py-3 text-center">Probabilidad</th>
                            <th class="px-4 py-3 text-center">Tipo</th>
                            <th class="px-4 py-3 text-center">Oportunidades</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($stages as $stage)
                            <tr class="hover:bg-slate-50" x-data="{ editing: false }">
                                <td class="px-4 py-3 text-center font-mono text-xs font-bold text-slate-800">
                                    {{ $stage->position }}
                                </td>
                                <td class="px-6 py-3 font-medium text-slate-900">
                                    <template x-if="!editing">
                                        <span>{{ $stage->name }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <form id="edit-stage-{{ $stage->id }}" action="{{ route('settings.pipelines.stages.update', [$pipeline, $stage]) }}" method="POST" class="flex items-center space-x-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="name" value="{{ $stage->name }}" required class="rounded border-slate-300 text-xs px-2 py-1 w-44" />
                                            <input type="number" name="position" value="{{ $stage->position }}" min="1" class="rounded border-slate-300 text-xs px-2 py-1 w-16" />
                                            @if (!$stage->is_won && !$stage->is_lost)
                                                <input type="number" name="probability" value="{{ $stage->probability }}" min="0" max="100" class="rounded border-slate-300 text-xs px-2 py-1 w-16" />
                                            @else
                                                <input type="hidden" name="probability" value="{{ $stage->probability }}" />
                                            @endif
                                            <select name="status" class="rounded border-slate-300 text-xs px-2 py-1">
                                                <option value="active" {{ $stage->status === 'active' ? 'selected' : '' }}>Activo</option>
                                                <option value="inactive" {{ $stage->status === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                            </select>
                                            <button type="submit" class="rounded bg-slate-900 px-2 py-1 text-xs text-white">Guardar</button>
                                            <button type="button" @click="editing = false" class="rounded bg-slate-200 px-2 py-1 text-xs text-slate-700">✕</button>
                                        </form>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-xs">
                                    <span class="rounded px-2 py-0.5 {{ $stage->is_won ? 'bg-emerald-100 text-emerald-800 font-bold' : ($stage->is_lost ? 'bg-rose-100 text-rose-800 font-bold' : 'bg-slate-100 text-slate-800') }}">
                                        {{ $stage->probability }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    @if ($stage->is_won)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">Ganada</span>
                                    @elseif ($stage->is_lost)
                                        <span class="rounded bg-rose-100 px-2 py-0.5 font-semibold text-rose-800">Perdida</span>
                                    @else
                                        <span class="text-slate-500">Abierta</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-mono text-xs">
                                    {{ $stage->opportunities_count }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <x-badge :color="$stage->status === 'active' ? 'emerald' : 'slate'">
                                        {{ $stage->status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-3 text-right space-x-2 text-xs">
                                    <button type="button" x-show="!editing" @click="editing = true" class="font-semibold text-blue-600 hover:text-blue-800">
                                        Editar
                                    </button>
                                    @if (!$stage->is_won && !$stage->is_lost && $stage->opportunities_count === 0)
                                        <form action="{{ route('settings.pipelines.stages.destroy', [$pipeline, $stage]) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta etapa?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold text-rose-600 hover:text-rose-800">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Formulario para añadir nueva etapa --}}
            <div class="mt-8 pt-6 border-t border-slate-200">
                <h3 class="text-sm font-semibold text-slate-900 mb-4">+ Añadir nueva etapa abierta</h3>
                <form action="{{ route('settings.pipelines.stages.store', $pipeline) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end text-xs">
                    @csrf
                    <div>
                        <x-label for="new_stage_name" value="Nombre de la etapa *" />
                        <x-input id="new_stage_name" name="name" type="text" placeholder="Ej: Negociación final" required class="mt-1 w-full text-xs" />
                    </div>
                    <div>
                        <x-label for="new_stage_prob" value="Probabilidad (0-100) % *" />
                        <x-input id="new_stage_prob" name="probability" type="number" min="0" max="100" value="60" required class="mt-1 w-full text-xs" />
                    </div>
                    <div>
                        <x-label for="new_stage_status" value="Estado inicial *" />
                        <select id="new_stage_status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-xs shadow-sm">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 font-semibold text-white shadow-sm hover:bg-slate-800">
                            Agregar etapa
                        </button>
                    </div>
                </form>
            </div>
        </x-card>
    </div>
@endsection
