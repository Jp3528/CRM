@php $user = auth()->user(); @endphp

<header class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button type="button" x-on:click="sidebarOpen = true" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Abrir menú">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
            <h1 class="text-sm font-semibold text-slate-900 sm:text-base">{{ $header ?? 'NexusCRM' }}</h1>
            @isset($subheader)<p class="text-xs text-slate-500">{{ $subheader }}</p>@endisset
        </div>
    </div>
    <div class="relative" x-data="{ open: false }">
        <button type="button" x-on:click="open = ! open" class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
            <span class="hidden font-medium sm:inline">{{ $user?->name }}</span>
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white">{{ strtoupper(substr($user?->name ?? '?', 0, 1)) }}</span>
        </button>
        <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute right-0 z-30 mt-2 w-52 rounded-md border border-slate-200 bg-white py-1 shadow-lg" style="display: none;">
            <div class="border-b border-slate-100 px-4 py-2">
                <p class="truncate text-sm font-medium text-slate-900">{{ $user?->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Mi perfil</a>
            <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Cerrar sesión</button>
            </form>
        </div>
    </div>
</header>
