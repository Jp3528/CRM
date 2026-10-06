@php
$user = auth()->user()?->loadMissing(['roles', 'team']);
$navSections = [
    ['title' => 'Comercial', 'items' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'can' => null, 'soon' => false],
        ['label' => 'Empresas', 'route' => 'companies.index', 'can' => 'companies.view', 'soon' => false],
        ['label' => 'Contactos', 'route' => 'contacts.index', 'can' => 'contacts.view', 'soon' => false],
        ['label' => 'Leads', 'route' => 'leads.index', 'can' => 'leads.view', 'soon' => false],
        ['label' => 'Oportunidades', 'route' => 'opportunities.index', 'can' => 'opportunities.view', 'soon' => false],
    ]],
    ['title' => 'Operación', 'items' => [
        ['label' => 'Tareas', 'route' => 'tasks.index', 'can' => 'tasks.view', 'soon' => false],
        ['label' => 'Actividades', 'route' => 'activities.index', 'can' => 'activities.view', 'soon' => false],
        ['label' => 'Calendario', 'route' => 'calendar.index', 'can_any' => ['tasks.view', 'activities.view'], 'soon' => false],
        ['label' => 'Tickets', 'route' => 'tickets.index', 'can' => 'tickets.view', 'soon' => false],
    ]],
    ['title' => 'Ventas', 'items' => [
        ['label' => 'Productos', 'route' => 'products.index', 'can' => 'products.view', 'soon' => false],
        ['label' => 'Cotizaciones', 'route' => 'quotes.index', 'can' => 'quotes.view', 'soon' => false],
        ['label' => 'Ventas', 'route' => 'sales.index', 'can' => 'sales.view', 'soon' => false],
        ['label' => 'Facturas', 'route' => 'invoices.index', 'can' => 'invoices.view', 'soon' => false],
    ]],
    ['title' => 'Marketing', 'items' => [
        ['label' => 'Campañas', 'route' => 'campaigns.index', 'can' => 'campaigns.view', 'soon' => false],
        ['label' => 'Plantillas', 'route' => 'templates.index', 'can' => 'templates.view', 'soon' => false],
        ['label' => 'Comunicaciones', 'route' => 'communications.index', 'can' => 'communications.view', 'soon' => false],
    ]],
    ['title' => 'Automatización', 'items' => [
        ['label' => 'Automatizaciones', 'route' => 'automations.index', 'can' => 'automations.view', 'soon' => false],
    ]],
    ['title' => 'Informes', 'items' => [
        ['label' => 'Reportes', 'route' => 'reports.index', 'can' => 'reports.view', 'soon' => false],
        ['label' => 'Forecast', 'route' => 'forecast.index', 'can' => 'reports.forecast', 'soon' => false],
    ]],
    ['title' => 'Datos', 'items' => [
        ['label' => 'Importaciones', 'route' => 'imports.index', 'can' => 'imports.view', 'soon' => false],
    ]],
];
$navAdmin = [
    ['label' => 'Usuarios', 'route' => 'admin.users.index', 'can' => 'users.view', 'soon' => false],
    ['label' => 'Equipos', 'route' => 'teams.index', 'can' => 'teams.view', 'soon' => false],
    ['label' => 'Roles y permisos', 'route' => 'roles.index', 'can' => 'roles.view', 'soon' => false],
    ['label' => 'Configuración', 'route' => 'settings.index', 'can' => 'settings.view', 'soon' => false],
    ['label' => 'Auditoría', 'route' => 'audit.index', 'can' => 'audit.view', 'soon' => false],
];
$isActive = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*');
$canSee = function (array $item) use ($user): bool {
    if (isset($item['can_any'])) {
        return (bool) $user && $user->hasAnyPermission($item['can_any']);
    }

    return ! ($item['can'] ?? null) || ((bool) $user && $user->hasPermission($item['can']));
};
$canSeeAdmin = (bool) $user && (
    $user->hasPermission('users.view') ||
    $user->hasPermission('teams.view') ||
    $user->hasPermission('roles.view') ||
    $user->hasPermission('settings.view') ||
    $user->hasPermission('audit.view')
);
@endphp

{{-- Sidebar escritorio --}}
<aside class="hidden w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-900 text-slate-200 lg:flex">
    <div class="flex h-16 items-center border-b border-slate-800 px-5">
        <a href="{{ route('dashboard') }}" class="text-base font-semibold tracking-tight text-white hover:text-cyan-400 transition-colors">
            {{ config('app.name', 'NexusCRM') }}
        </a>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm focus:outline-none" aria-label="Navegación principal">
        @foreach ($navSections as $section)
            @php $visible = collect($section['items'])->filter(fn ($i) => $canSee($i)); @endphp
            @if ($visible->isNotEmpty())
                <p class="px-2 pb-2 {{ $loop->first ? '' : 'pt-4 ' }}text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section['title'] }}</p>
                <ul class="space-y-1">
                    @foreach ($visible as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center justify-between rounded-md px-3 py-2 transition-colors {{ $isActive($item['route']) ? 'bg-slate-800 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
        @if ($canSeeAdmin)
            <p class="px-2 pb-2 pt-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administración</p>
            <ul class="space-y-1">
                @foreach ($navAdmin as $item)
                    @if ($canSee($item))
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center justify-between rounded-md px-3 py-2 transition-colors {{ $isActive($item['route']) ? 'bg-slate-800 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
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
        <p class="font-medium text-slate-300 truncate">{{ $user?->name }}</p>
        <p class="mt-0.5 truncate text-slate-500">{{ $user?->roles->first()?->name ?? 'Sin rol' }}</p>
    </div>
</aside>

{{-- Sidebar móvil accesible --}}
<div id="mobile-sidebar"
     x-show="sidebarOpen" x-cloak
     class="fixed inset-0 z-40 lg:hidden"
     role="dialog" aria-modal="true" aria-label="Menú principal de navegación"
     @keydown.escape.window="sidebarOpen = false">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
         x-show="sidebarOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"></div>

    <aside class="relative flex w-72 max-w-[80vw] flex-1 flex-col bg-slate-900 text-slate-200 shadow-2xl h-full"
           x-show="sidebarOpen"
           x-transition:enter="transition ease-in-out duration-300 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in-out duration-300 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full">
        <div class="flex h-16 items-center justify-between border-b border-slate-800 px-5">
            <span class="text-base font-semibold text-white">{{ config('app.name', 'NexusCRM') }}</span>
            <button type="button" @click="sidebarOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors" aria-label="Cerrar menú">
                ✕
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm" aria-label="Navegación móvil">
            @foreach ($navSections as $section)
                @php $visible = collect($section['items'])->filter(fn ($i) => $canSee($i)); @endphp
                @if ($visible->isNotEmpty())
                    <p class="px-2 pb-2 {{ $loop->first ? '' : 'pt-4 ' }}text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section['title'] }}</p>
                    <ul class="space-y-1">
                        @foreach ($visible as $item)
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 transition-colors {{ $isActive($item['route']) ? 'bg-slate-800 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
            @if ($canSeeAdmin)
                <p class="px-2 pb-2 pt-6 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Administración</p>
                <ul class="space-y-1">
                    @foreach ($navAdmin as $item)
                        @if ($canSee($item))
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 transition-colors {{ $isActive($item['route']) ? 'bg-slate-800 text-white font-medium' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
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
            <p class="font-medium text-slate-300 truncate">{{ $user?->name }}</p>
            <p class="mt-0.5 truncate text-slate-500">{{ $user?->roles->first()?->name ?? 'Sin rol' }}</p>
        </div>
    </aside>
</div>
