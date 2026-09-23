@extends('layouts.app', ['header' => 'Nuevo ticket', 'subheader' => 'Abrir caso de soporte'])

@section('title', 'Nuevo ticket')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Tickets', 'url' => route('tickets.index')], ['label' => 'Nuevo']]" />
@endsection

@section('content')
    <x-card title="Datos del ticket" subtitle="Se crea en estado Nuevo">
        <form method="POST" action="{{ route('tickets.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('tickets._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Abrir ticket</x-button>
                <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
