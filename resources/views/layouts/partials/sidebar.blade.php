@php
$user = auth()->user()?->loadMissing(['roles', 'team']);
$navMain = [
    ['label' => 'Dashboard', 'route' => 'dashboard', 'can' => null, 'soon' => false],
    ['label' => 'Empresas', 'route' => 'companies.index', 'can' => 'companies.view', 'soon' => false],
    ['label' => 'Contactos', 'route' => 'contacts.index', 'can' => 'contacts.view', 'soon' => false],
    ['label' => 'Leads', 'route' => 'leads.index', 'can' => 'leads.view', 'soon' => false],
    ['label' => 'Oportunidades', 'route' => 'opportunities.index', 'can' => 'opportunities.view', 'soon' => false],
    ['label' => 'Tareas', 'route' => 'tasks.index', 'can' => 'tasks.view', 'soon' => true],
    ['label' => 'Actividades', 'route' => 'activities.index', 'can' => null, 'soon' => true],
];
$navAdmin = [
    ['label' => 'Usuarios', 'route' => 'admin.users.index', 'can' => 'users.view', 'soon' => false],
    ['label' => 'Equipos', 'route' => 'teams.index', 'can' => 'users.view', 'soon' => true],
    ['label' => 'Roles y permisos', 'route' => 'roles.index', 'can' => 'users.view', 'soon' => true],
    ['label' => 'Configuración', 'route' => 'settings.index', 'can' => 'users.view', 'soon' => true],
];
$isActive = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*');
@endphp

{{-- Sidebar escritorio --}}
<aside class="hidden w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-900 text-slate-200 lg:flex">
    <div class="flex h-16 items-center border-b border-slate-800 px-5">
        <span class="text-base font-semibold tracking-tight text-white">{{ config('app.name', 'NexusCRM') }}</span>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
        <p class="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">CRM</p>
        <ul class="space-y-1">
            @foreach ($navMain as $item)
                @if (! $item['can'] || ($user && $user->hasPermission($item['can'])))
                    <li>
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                            <span>{{ $item['label'] }}</span>
                            @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($user && $user->hasPermission('users.view'))
            <p class="px-2 pb-2 pt-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administración</p>
            <ul class="space-y-1">
                @foreach ($navAdmin as $item)
                    @if ($user->hasPermission($item['can']))
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </nav>
    <div class="border-t border-slate-800 px-5 py-4 text-xs text-slate-400">
        <p>{{ $user?->name }}</p>
        <p class="mt-0.5 truncate">{{ $user?->roles->first()?->name ?? 'Sin rol' }}</p>
    </div>
</aside>

{{-- Sidebar móvil --}}
<div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 lg:hidden" style="display: none;">
    <div class="absolute inset-0 bg-slate-900/60" x-on:click="sidebarOpen = false"></div>
    <aside class="absolute inset-y-0 left-0 flex w-72 flex-col bg-slate-900 text-slate-200">
        <div class="flex h-16 items-center justify-between border-b border-slate-800 px-5">
            <span class="text-base font-semibold text-white">{{ config('app.name', 'NexusCRM') }}</span>
            <button type="button" x-on:click="sidebarOpen = false" class="rounded p-1 text-slate-400 hover:text-white" aria-label="Cerrar menú">✕</button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
            <ul class="space-y-1">
                @foreach ($navMain as $item)
                    @if (! $item['can'] || ($user && $user->hasPermission($item['can'])))
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
            @if ($user && $user->hasPermission('users.view'))
                <p class="px-2 pb-2 pt-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administración</p>
                <ul class="space-y-1">
                    @foreach ($navAdmin as $item)
                        @if ($user->hasPermission($item['can']))
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </nav>
    </aside>
</div>
