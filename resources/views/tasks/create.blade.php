@extends('layouts.app', ['header' => 'Nueva tarea', 'subheader' => 'Crear seguimiento'])

@section('title', 'Nueva tarea')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tareas', 'url' => route('tasks.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la tarea" subtitle="El creador será tu usuario automáticamente">
        <form method="POST" action="{{ route('tasks.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('tasks._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar tarea</x-button>
                <a href="{{ route('tasks.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
