@extends('layouts.app', ['header' => 'Nueva automatizacion', 'subheader' => 'Regla interna en borrador'])

@section('title', 'Nueva automatizacion')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones', 'url' => route('automations.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Definir automatizacion" subtitle="Se guarda en borrador; activala cuando la config sea valida">
        <form method="POST" action="{{ route('automations.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('automations._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Crear borrador</x-button>
                <a href="{{ route('automations.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
