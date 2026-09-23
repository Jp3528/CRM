@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="text-lg font-semibold">Iniciar sesión</h1>
    <p class="mb-5 mt-1 text-sm text-slate-500">Accede con tu cuenta corporativa.</p>

    <x-flash />

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="email" value="Correo electrónico" />
            <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-label for="password" value="Contraseña" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs text-slate-500 hover:text-slate-800 hover:underline">¿Olvidaste tu contraseña?</a>
                @endif
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="rounded border-slate-300">
            Recuérdame en este equipo
        </label>

        <x-button class="w-full">Entrar</x-button>
    </form>
@endsection
