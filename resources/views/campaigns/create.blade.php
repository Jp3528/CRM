@extends('layouts.app', ['header' => 'Nueva campaña', 'subheader' => 'Marketing interno'])

@section('title', 'Nueva campaña')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Campañas', 'url' => route('campaigns.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la campaña" subtitle="Se crea en borrador">
        <form method="POST" action="{{ route('campaigns.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('campaigns._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Crear campaña</x-button>
                <a href="{{ route('campaigns.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
