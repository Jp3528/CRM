@extends('layouts.app', ['header' => $module, 'subheader' => 'Módulo pendiente de fases posteriores'])

@section('title', $module)

@section('content')
    <x-card :title="$module" subtitle="Próximamente">
        <p class="text-sm text-slate-600">
            El módulo <strong>{{ $module }}</strong> aún no existe. Esta es una página placeholder controlada de Fase 2:
            no hay CRUD ni datos comerciales aquí.
        </p>
        <div class="mt-4">
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-700 hover:underline">Volver al dashboard</a>
        </div>
    </x-card>
@endsection
