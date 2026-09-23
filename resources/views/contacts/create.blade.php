@extends('layouts.app', ['header' => 'Nuevo contacto', 'subheader' => 'Crear registro de contacto'])

@section('title', 'Nuevo contacto')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Contactos', 'url' => route('contacts.index')], ['label' => 'Nuevo']]" />
@endsection

@section('content')
    <x-card title="Datos del contacto" subtitle="Los campos marcados con * son obligatorios">
        <form method="POST" action="{{ route('contacts.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('contacts._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar contacto</x-button>
                <a href="{{ route('contacts.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
