@extends('layouts.app', ['header' => 'Editar actividad', 'subheader' => $activity->subject ?? ucfirst($activity->type)])

@section('title', 'Editar actividad')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Actividades', 'url' => route('activities.index')], ['label' => $activity->subject ?? 'Detalle', 'url' => route('activities.show', $activity)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar actividad">
        <form method="POST" action="{{ route('activities.update', $activity) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('activities._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('activities.show', $activity) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
