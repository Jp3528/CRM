@extends('layouts.app', ['header' => $module, 'subheader' => 'Módulo pendiente'])

@section('title', $module)

@section('content')
    <x-card :title="$module" subtitle="Próximamente">
        <p class="text-sm text-slate-600">
            El módulo <strong>{{ $module }}</strong> aún no está disponible:
            no hay funcionalidad ni datos aquí todavía.
        </p>
        <div class="mt-4">
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-700 hover:underline">Volver al dashboard</a>
        </div>
    </x-card>
@endsection
