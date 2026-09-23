@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <h1 class="text-lg font-semibold">Recuperar contraseña</h1>
    <p class="mb-5 mt-1 text-sm text-slate-500">Te enviaremos un enlace para restablecerla. Estructura lista; el envío real se configura en fases posteriores.</p>

    <x-flash />

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="email" value="Correo electrónico" />
            <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>
        <x-button class="w-full">Enviar enlace</x-button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="text-slate-600 hover:underline">Volver al login</a></p>
@endsection
