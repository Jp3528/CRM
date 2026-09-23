@extends('layouts.app', ['header' => 'Editar oportunidad', 'subheader' => $opportunity->name])

@section('title', 'Editar oportunidad')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Oportunidades', 'url' => route('opportunities.index')], ['label' => $opportunity->name, 'url' => route('opportunities.show', $opportunity)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $opportunity->name }}" subtitle="La etapa se cambia desde la ficha o el Kanban (con historial)">
        <form method="POST" action="{{ route('opportunities.update', $opportunity) }}" class="grid gap-4 md:grid-cols-2"
            x-data="oppForm()" x-init="filterContacts()">
            @csrf
            @method('PUT')
            @include('opportunities._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('opportunities.show', $opportunity) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
