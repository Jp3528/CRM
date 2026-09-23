@extends('layouts.app', ['header' => 'Editar lead', 'subheader' => $lead->full_name])

@section('title', 'Editar lead')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Leads', 'url' => route('leads.index')], ['label' => $lead->full_name, 'url' => route('leads.show', $lead)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $lead->full_name }}">
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('leads._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('leads.show', $lead) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
