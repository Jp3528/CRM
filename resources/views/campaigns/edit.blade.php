@extends('layouts.app', ['header' => 'Editar campaña', 'subheader' => $campaign->name])

@section('title', 'Editar campaña')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Campañas', 'url' => route('campaigns.index')], ['label' => $campaign->name, 'url' => route('campaigns.show', $campaign)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar campaña" subtitle="Transiciones simples: draft → scheduled/active → paused/completed/cancelled">
        <form method="POST" action="{{ route('campaigns.update', $campaign) }}" class="grid gap-4 md:grid-cols-2">
            @csrf @method('PUT')
            @include('campaigns._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar</x-button>
                <a href="{{ route('campaigns.show', $campaign) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
