@extends('layouts.app', ['header' => 'Editar contacto', 'subheader' => $contact->full_name])

@section('title', 'Editar contacto')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Contactos', 'url' => route('contacts.index')], ['label' => $contact->full_name, 'url' => route('contacts.show', $contact)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $contact->full_name }}">
        <form method="POST" action="{{ route('contacts.update', $contact) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('contacts._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('contacts.show', $contact) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
