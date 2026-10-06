@extends('layouts.app', ['header' => 'Pipelines comerciales', 'subheader' => 'Configuración de pipelines y etapas de venta'])

@section('title', 'Pipelines comerciales')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Pipelines'],
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

        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-600">
                Los pipelines definen las etapas de calificación y probabilidad en las oportunidades de venta y en el Kanban comercial.
            </p>
            <a href="{{ route('settings.pipelines.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                + Nuevo pipeline
            </a>
        </div>

        <x-card>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700">
                        <tr>
                            <th class="px-6 py-3">Nombre</th>
                            <th class="px-6 py-3">Estado</th>
                            <th class="px-6 py-3 text-center">Etapas</th>
                            <th class="px-6 py-3 text-center">Oportunidades</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($pipelines as $pipeline)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('settings.pipelines.show', $pipeline) }}" class="font-semibold text-slate-900 hover:underline">
                                            {{ $pipeline->name }}
                                        </a>
                                        @if ($pipeline->is_default)
                                            <span class="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-800">Predeterminado</span>
                                        @endif
                                    </div>
                                    @if ($pipeline->description)
                                        <p class="text-xs text-slate-500 mt-0.5">{{ $pipeline->description }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <x-badge :color="$pipeline->status === 'active' ? 'emerald' : 'slate'">
                                        {{ $pipeline->status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs">
                                    {{ $pipeline->stages_count }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs">
                                    {{ $pipeline->opportunities_count }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 text-xs">
                                    <a href="{{ route('settings.pipelines.show', $pipeline) }}" class="font-semibold text-blue-600 hover:text-blue-800">
                                        Ver etapas
                                    </a>
                                    <a href="{{ route('settings.pipelines.edit', $pipeline) }}" class="font-semibold text-slate-600 hover:text-slate-900">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                    No hay pipelines registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
