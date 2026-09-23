@extends('layouts.app', ['header' => 'Editar plantilla', 'subheader' => $template->name])

@section('title', 'Editar plantilla')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Plantillas', 'url' => route('templates.index')], ['label' => $template->name, 'url' => route('templates.show', $template)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar plantilla">
        <form method="POST" action="{{ route('templates.update', $template) }}" class="grid gap-4 md:grid-cols-2">
            @csrf @method('PUT')
            @include('templates._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar</x-button>
                <a href="{{ route('templates.show', $template) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
