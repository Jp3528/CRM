@extends('layouts.app', ['header' => 'Nuevo lead', 'subheader' => 'Registrar prospecto'])

@section('title', 'Nuevo lead')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Leads', 'url' => route('leads.index')], ['label' => 'Nuevo']]" />
@endsection

@section('content')
    <x-card title="Datos del lead" subtitle="Los campos marcados con * son obligatorios">
        <form method="POST" action="{{ route('leads.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('leads._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar lead</x-button>
                <a href="{{ route('leads.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
