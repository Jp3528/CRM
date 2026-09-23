@extends('layouts.app', ['header' => 'Editar automatizacion', 'subheader' => $automation->name])

@section('title', 'Editar automatizacion')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Automatizaciones', 'url' => route('automations.index')], ['label' => $automation->name, 'url' => route('automations.show', $automation)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar (pausada o borrador)" subtitle="Las activas deben pausarse antes de editarse">
        <form method="POST" action="{{ route('automations.update', $automation) }}" class="grid gap-4 md:grid-cols-2">
            @csrf @method('PUT')
            @include('automations._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar</x-button>
                <a href="{{ route('automations.show', $automation) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
