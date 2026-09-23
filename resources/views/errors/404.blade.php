@extends('layouts.app', ['header' => '404 — No encontrado'])

@section('title', 'No encontrado')

@section('content')
    <x-card title="Página no encontrada" subtitle="La ruta solicitada no existe">
        <p class="text-sm text-slate-600">Verifica la URL o vuelve al dashboard.</p>
        <div class="mt-4 text-sm">
            <a href="{{ route('dashboard') }}" class="text-slate-700 hover:underline">Ir al dashboard</a>
        </div>
    </x-card>
@endsection
