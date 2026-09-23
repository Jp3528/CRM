@extends('layouts.app', ['header' => 'Editar venta', 'subheader' => $sale->number])

@section('title', 'Editar venta')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ventas', 'url' => route('sales.index')], ['label' => $sale->number, 'url' => route('sales.show', $sale)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar {{ $sale->number }}">
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('sales.update', $sale) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            @include('sales._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar cambios</x-button>
                <a href="{{ route('sales.show', $sale) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
