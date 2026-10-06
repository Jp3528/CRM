@extends('layouts.app', ['header' => 'Categorías de tickets', 'subheader' => 'Catálogo de tipificación de incidentes y requerimientos de soporte'])

@section('title', 'Categorías de tickets')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Configuración', 'url' => route('settings.index')],
        ['label' => 'Categorías de tickets'],
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

        {{-- Formulario para crear nueva categoría --}}
        <x-card title="Añadir nueva categoría de soporte" subtitle="El archivado preserva los tickets históricos asociados">
            <form action="{{ route('settings.ticket-categories.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end text-xs">
                @csrf
                <div>
                    <x-label for="name" value="Nombre de la categoría *" />
                    <x-input id="name" name="name" type="text" placeholder="Ej: Facturación y Pagos" required class="mt-1 w-full text-xs" />
                </div>
                <div>
                    <x-label for="status" value="Estado *" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-xs shadow-sm">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 font-semibold text-white shadow-sm hover:bg-slate-800">
                        Crear categoría
                    </button>
                </div>
            </form>
        </x-card>

        {{-- Listado de categorías --}}
        <x-card title="Categorías de soporte registradas">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-700">
                        <tr>
                            <th class="px-6 py-3">Nombre</th>
                            <th class="px-6 py-3">Slug</th>
                            <th class="px-6 py-3 text-center">Tickets vinculados</th>
                            <th class="px-6 py-3 text-center">Estado</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($categories as $category)
                            <tr class="hover:bg-slate-50" x-data="{ editing: false }">
                                <td class="px-6 py-4 font-semibold text-slate-900">
                                    <template x-if="!editing">
                                        <span>{{ $category->name }}</span>
                                    </template>
                                    <template x-if="editing">
                                        <form id="edit-cat-{{ $category->id }}" action="{{ route('settings.ticket-categories.update', $category) }}" method="POST" class="flex items-center space-x-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="name" value="{{ $category->name }}" required class="rounded border-slate-300 text-xs px-2 py-1 w-44" />
                                            <select name="status" class="rounded border-slate-300 text-xs px-2 py-1">
                                                <option value="active" {{ $category->status === 'active' ? 'selected' : '' }}>Activo</option>
                                                <option value="inactive" {{ $category->status === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                            </select>
                                            <button type="submit" class="rounded bg-slate-900 px-2 py-1 text-xs text-white">Guardar</button>
                                            <button type="button" @click="editing = false" class="rounded bg-slate-200 px-2 py-1 text-xs text-slate-700">✕</button>
                                        </form>
                                    </template>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-500">
                                    {{ $category->slug }}
                                </td>
                                <td class="px-6 py-4 text-center font-mono text-xs">
                                    {{ $category->tickets_count }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <x-badge :color="$category->status === 'active' ? 'emerald' : 'slate'">
                                        {{ $category->status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </x-badge>
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 text-xs">
                                    <button type="button" x-show="!editing" @click="editing = true" class="font-semibold text-blue-600 hover:text-blue-800">
                                        Editar
                                    </button>
                                    @if ($category->tickets_count === 0)
                                        <form action="{{ route('settings.ticket-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta categoría?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-semibold text-rose-600 hover:text-rose-800">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-400">
                                    No hay categorías registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="mt-4 border-t border-slate-200 pt-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </x-card>
    </div>
@endsection
