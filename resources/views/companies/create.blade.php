@extends('layouts.app', ['header' => 'Nueva empresa', 'subheader' => 'Crear registro de empresa'])

@section('title', 'Nueva empresa')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Empresas', 'url' => route('companies.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la empresa" subtitle="Los campos marcados con * son obligatorios">
        <form method="POST" action="{{ route('companies.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('companies._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar empresa</x-button>
                <a href="{{ route('companies.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
