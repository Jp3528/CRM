@extends('layouts.app', ['header' => 'Registrar actividad', 'subheader' => 'Llamada, email, reunión o nota'])

@section('title', 'Registrar actividad')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Actividades', 'url' => route('activities.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la actividad" subtitle="Quedarás registrado como autor automáticamente">
        <form method="POST" action="{{ route('activities.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('activities._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar actividad</x-button>
                <a href="{{ route('activities.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
