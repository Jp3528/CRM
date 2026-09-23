@extends('layouts.app', ['header' => 'Usuarios', 'subheader' => 'Sección administrativa (placeholder operativo)'])

@section('title', 'Usuarios')

@section('content')
    <x-card title="Control de acceso" subtitle="Requiere permiso users.view — autorización verificada en backend">
        <p class="text-sm text-slate-600">Usuarios registrados: <strong>{{ $total }}</strong></p>
        <p class="mt-2 text-sm text-slate-500">La gestión completa de usuarios (CRUD, asignación de roles) corresponde a fases posteriores. Esta vista solo demuestra que la autorización backend funciona.</p>
    </x-card>
@endsection
