@extends('layouts.app', ['header' => 'Nueva oportunidad', 'subheader' => 'Crear registro comercial'])

@section('title', 'Nueva oportunidad')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Oportunidades', 'url' => route('opportunities.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la oportunidad" subtitle="Los campos marcados con * son obligatorios">
        <form method="POST" action="{{ route('opportunities.store') }}" class="grid gap-4 md:grid-cols-2"
            x-data="oppForm()" x-init="filterContacts(); filterStages();">
            @csrf
            @include('opportunities._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar oportunidad</x-button>
                <a href="{{ route('opportunities.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
