@extends('layouts.app', ['header' => 'Nuevo producto', 'subheader' => 'Agregar al catálogo'])

@section('title', 'Nuevo producto')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Productos', 'url' => route('products.index')], ['label' => 'Nuevo']]" />
@endsection

@section('content')
    <x-card title="Datos del producto">
        <form method="POST" action="{{ route('products.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('products._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar producto</x-button>
                <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
