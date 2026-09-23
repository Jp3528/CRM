@extends('layouts.app', ['header' => 'Editar empresa', 'subheader' => $company->trade_name])

@section('title', 'Editar empresa')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Empresas', 'url' => route('companies.index')], ['label' => $company->trade_name, 'url' => route('companies.show', $company)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $company->trade_name }}">
        <form method="POST" action="{{ route('companies.update', $company) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('companies._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('companies.show', $company) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
