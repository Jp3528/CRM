@extends('layouts.app', ['header' => 'Nueva plantilla', 'subheader' => 'Texto plano con variables seguras'])

@section('title', 'Nueva plantilla')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Plantillas', 'url' => route('templates.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la plantilla">
        <form method="POST" action="{{ route('templates.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('templates._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Crear plantilla</x-button>
                <a href="{{ route('templates.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
