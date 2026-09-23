<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title', 'Acceso')).' — '.config('app.name', 'NexusCRM') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <p class="text-lg font-semibold tracking-tight">{{ config('app.name', 'NexusCRM') }}</p>
                <p class="mt-1 text-sm text-slate-500">CRM empresarial · acceso interno</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                @yield('content')
            </div>
            <p class="mt-4 text-center text-xs text-slate-400">Uso interno. Si no tienes cuenta, contacta a tu administrador.</p>
        </div>
    </div>
</body>
</html>
