@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <h1 class="text-lg font-semibold">Restablecer contraseña</h1>
    <p class="mb-5 mt-1 text-sm text-slate-500">Define tu nueva contraseña.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <x-label for="email" value="Correo electrónico" />
            <input id="email" name="email" type="email" required value="{{ old('email', $email) }}"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>
        <div>
            <x-label for="password" value="Nueva contraseña" />
            <input id="password" name="password" type="password" required autocomplete="new-password"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>
        <div>
            <x-label for="password_confirmation" value="Confirmar contraseña" />
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        </div>
        <x-button class="w-full">Restablecer</x-button>
    </form>
@endsection
