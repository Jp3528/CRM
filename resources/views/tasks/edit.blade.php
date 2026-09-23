@extends('layouts.app', ['header' => 'Editar tarea', 'subheader' => $task->title])

@section('title', 'Editar tarea')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tareas', 'url' => route('tasks.index')], ['label' => $task->title, 'url' => route('tasks.show', $task)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $task->title }}">
        <form method="POST" action="{{ route('tasks.update', $task) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('tasks._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('tasks.show', $task) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
