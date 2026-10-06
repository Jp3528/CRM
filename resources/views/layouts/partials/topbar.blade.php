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
    <div class="flex items-center gap-2">
        {{-- Campana de notificaciones internas --}}
        <div class="relative" x-data="{
            open: false,
            count: {{ auth()->user()?->unreadNotifications()->count() ?? 0 }},
            recent: [],
            loading: false,
            loadRecent() {
                if (!this.open) {
                    this.loading = true;
                    fetch('{{ route('notifications.unread-count') }}')
                        .then(r => r.json())
                        .then(data => {
                            this.count = data.unread_count;
                            this.recent = data.recent;
                            this.loading = false;
                        })
                        .catch(() => { this.loading = false; });
                }
                this.open = !this.open;
            }
        }">
            <button type="button" @click="loadRecent()" class="relative rounded-lg p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Notificaciones">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <template x-if="count > 0">
                    <span class="absolute top-1 right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-cyan-600 px-1 text-[10px] font-bold text-white shadow-xs" x-text="count"></span>
                </template>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-slate-200 bg-white py-2 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">Notificaciones</span>
                    <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-cyan-700 hover:underline">Ver todas</a>
                </div>

                <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                    <template x-if="loading">
                        <div class="p-4 text-center text-xs text-slate-400">Cargando avisos...</div>
                    </template>
                    <template x-if="!loading && recent.length === 0">
                        <div class="p-4 text-center text-xs text-slate-400">No hay notificaciones recientes</div>
                    </template>
                    <template x-for="item in recent" :key="item.id">
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-900 truncate" x-text="item.title"></span>
                                <span class="text-[10px] text-slate-400" x-text="item.created_at"></span>
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5 line-clamp-2" x-text="item.message"></p>
                        </div>
                    </template>
                </div>

                <div class="border-t border-slate-100 px-4 py-2 text-center bg-slate-50">
                    <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-slate-700 hover:text-cyan-700">
                        Bandeja completa de avisos →
                    </a>
                </div>
            </div>
        </div>

        {{-- Menú de usuario --}}
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
                <a href="{{ route('notifications.index') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Notificaciones</a>
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Mi perfil</a>
                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</header>
