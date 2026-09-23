@extends('layouts.app', ['header' => 'Nueva venta', 'subheader' => 'Venta manual'])

@section('title', 'Nueva venta')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Ventas', 'url' => route('sales.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Datos de la venta" subtitle="Los totales se calculan en el backend al guardar">
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('sales.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @include('sales._form')
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar venta</x-button>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
