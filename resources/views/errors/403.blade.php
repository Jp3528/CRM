@extends('layouts.app', ['header' => '403 — Sin permiso'])

@section('title', 'Sin permiso')

@section('content')
    <x-card title="Acceso denegado" subtitle="No tienes permiso para ver esta sección">
        <p class="text-sm text-slate-600">Si crees que es un error, contacta a tu administrador.</p>
        <div class="mt-4 flex gap-3 text-sm">
            <a href="{{ route('dashboard') }}" class="text-slate-700 hover:underline">Ir al dashboard</a>
            <a href="{{ route('login') }}" class="text-slate-700 hover:underline">Ir al login</a>
        </div>
    </x-card>
@endsection
