@extends('layouts.app', ['header' => 'Registro de Auditoría', 'subheader' => 'Trazabilidad de acciones administrativas, transaccionales y de seguridad'])

@section('title', 'Registro de Auditoría')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Auditoría']]" />
@endsection

@section('content')
<div class="space-y-6" x-data="{ selectedLog: null }">

    {{-- Filtros de búsqueda --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <form method="GET" action="{{ route('audit.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Entidad</label>
                <select name="entity_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="">Todas las entidades</option>
                    @foreach ($entityTypes as $type)
                        <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ class_basename($type) }} ({{ $type }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Acción</label>
                <select name="action" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="">Todas las acciones</option>
                    @foreach ($actions as $act)
                        <option value="{{ $act }}" @selected(request('action') === $act)>{{ $act }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Usuario</label>
                <select name="user_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
                    <option value="">Todos los usuarios</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Desde</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Hasta</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-800 focus:border-cyan-500 focus:ring-cyan-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-cyan-800 transition-colors">
                    Filtrar
                </button>
                <a href="{{ route('audit.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    {{-- Tabla de registros --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-6 py-3.5">Fecha y Hora</th>
                        <th scope="col" class="px-6 py-3.5">Usuario / Actor</th>
                        <th scope="col" class="px-6 py-3.5">Acción</th>
                        <th scope="col" class="px-6 py-3.5">Entidad Afectada</th>
                        <th scope="col" class="px-6 py-3.5">IP</th>
                        <th scope="col" class="px-6 py-3.5 text-right">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-50/75 transition-colors">
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-mono text-slate-600">
                                {{ $log->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                @if ($log->user)
                                    <div class="font-medium text-slate-900">{{ $log->user->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->user->email }}</div>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                        Sistema / Automatización
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold
                                    @if(str_contains($log->action, 'delete') || str_contains($log->action, 'destroy'))
                                        bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20
                                    @elseif(str_contains($log->action, 'create') || str_contains($log->action, 'store'))
                                        bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20
                                    @elseif(str_contains($log->action, 'login') || str_contains($log->action, 'export'))
                                        bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-600/20
                                    @else
                                        bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-600/10
                                    @endif">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-slate-800">
                                <span class="font-medium">{{ class_basename($log->entity_type) }}</span>
                                @if ($log->entity_id)
                                    <span class="text-xs text-slate-500 font-mono">#{{ $log->entity_id }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-xs font-mono text-slate-500">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <button type="button"
                                    @click="selectedLog = {{ json_encode([
                                        'id' => $log->id,
                                        'action' => $log->action,
                                        'entity_type' => $log->entity_type,
                                        'entity_id' => $log->entity_id,
                                        'user' => $log->user ? $log->user->name . ' (' . $log->user->email . ')' : 'Sistema',
                                        'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                                        'ip' => $log->ip_address,
                                        'user_agent' => $log->user_agent,
                                        'old_values' => $log->old_values,
                                        'new_values' => $log->new_values,
                                    ]) }}"
                                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:border-slate-300 transition-colors">
                                    Ver diferencias
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No se encontraron registros de auditoría coincidentes con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Alpine.js para inspección de diferencias --}}
    <div x-show="selectedLog !== null"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 p-4 sm:p-6 md:p-20 flex items-center justify-center"
         @keydown.escape.window="selectedLog = null">
        <div class="relative w-full max-w-2xl transform rounded-2xl bg-white p-6 shadow-2xl transition-all border border-slate-200"
             @click.outside="selectedLog = null">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                <div>
                    <h3 class="text-base font-semibold text-slate-900" x-text="'Auditoría #' + (selectedLog ? selectedLog.id : '')"></h3>
                    <p class="text-xs text-slate-500" x-text="(selectedLog ? selectedLog.action : '') + ' • ' + (selectedLog ? selectedLog.created_at : '')"></p>
                </div>
                <button type="button" @click="selectedLog = null" class="rounded-lg p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                    <span class="sr-only">Cerrar</span>
                    ✕
                </button>
            </div>

            <div class="mt-4 space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100">
                    <div>
                        <span class="font-semibold text-slate-600">Usuario:</span>
                        <span class="text-slate-900 block" x-text="selectedLog ? selectedLog.user : ''"></span>
                    </div>
                    <div>
                        <span class="font-semibold text-slate-600">Entidad:</span>
                        <span class="text-slate-900 block font-mono" x-text="selectedLog ? (selectedLog.entity_type + ' #' + (selectedLog.entity_id || '—')) : ''"></span>
                    </div>
                    <div>
                        <span class="font-semibold text-slate-600">Dirección IP:</span>
                        <span class="text-slate-900 block font-mono" x-text="selectedLog ? (selectedLog.ip || '—') : ''"></span>
                    </div>
                    <div>
                        <span class="font-semibold text-slate-600">Navegador / Cliente:</span>
                        <span class="text-slate-900 block truncate" x-text="selectedLog ? (selectedLog.user_agent || '—') : ''" :title="selectedLog ? selectedLog.user_agent : ''"></span>
                    </div>
                </div>

                {{-- Valores anteriores vs nuevos --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span>Valores anteriores</span>
                            <span class="text-rose-600 font-mono text-[10px]">PREVIO</span>
                        </div>
                        <pre class="bg-rose-50/50 border border-rose-100 text-rose-950 p-3 rounded-lg text-xs font-mono whitespace-pre-wrap overflow-x-auto min-h-[120px]"
                             x-text="selectedLog && selectedLog.old_values ? JSON.stringify(selectedLog.old_values, null, 2) : '(Sin valores previos o creación)'"></pre>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                            <span>Valores nuevos</span>
                            <span class="text-emerald-600 font-mono text-[10px]">NUEVO</span>
                        </div>
                        <pre class="bg-emerald-50/50 border border-emerald-100 text-emerald-950 p-3 rounded-lg text-xs font-mono whitespace-pre-wrap overflow-x-auto min-h-[120px]"
                             x-text="selectedLog && selectedLog.new_values ? JSON.stringify(selectedLog.new_values, null, 2) : '(Sin valores nuevos o eliminación)'"></pre>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" @click="selectedLog = null" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
