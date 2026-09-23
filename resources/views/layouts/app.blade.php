<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title', 'Dashboard')).' — '.config('app.name', 'NexusCRM') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: false }">
        @include('layouts.partials.sidebar')

        <div class="flex min-w-0 flex-1 flex-col">
            @include('layouts.partials.topbar')

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
                @hasSection('breadcrumbs')<div class="mb-4">@yield('breadcrumbs')</div>@endif

                <div class="space-y-4">
                    <x-flash />
                    @yield('content')
                </div>

                <footer class="mt-10 border-t border-slate-200 pt-4 text-xs text-slate-400">
                    {{ config('app.name', 'NexusCRM') }} · {{ now()->format('Y-m-d H:i') }}
                </footer>
            </main>
        </div>
    </div>
</body>
</html>
