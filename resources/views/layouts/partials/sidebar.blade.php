@php
$user = auth()->user()?->loadMissing(['roles', 'team']);
$navSections = [
    ['title' => 'CRM', 'items' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'can' => null, 'soon' => false],
        ['label' => 'Empresas', 'route' => 'companies.index', 'can' => 'companies.view', 'soon' => false],
        ['label' => 'Contactos', 'route' => 'contacts.index', 'can' => 'contacts.view', 'soon' => false],
        ['label' => 'Leads', 'route' => 'leads.index', 'can' => 'leads.view', 'soon' => false],
        ['label' => 'Oportunidades', 'route' => 'opportunities.index', 'can' => 'opportunities.view', 'soon' => false],
    ]],
    ['title' => 'Trabajo', 'items' => [
        ['label' => 'Tareas', 'route' => 'tasks.index', 'can' => 'tasks.view', 'soon' => false],
        ['label' => 'Actividades', 'route' => 'activities.index', 'can' => 'activities.view', 'soon' => false],
        ['label' => 'Calendario', 'route' => 'calendar.index', 'can_any' => ['tasks.view', 'activities.view'], 'soon' => false],
    ]],
    ['title' => 'Soporte', 'items' => [
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
];
$navAdmin = [
    ['label' => 'Usuarios', 'route' => 'admin.users.index', 'can' => 'users.view', 'soon' => false],
    ['label' => 'Equipos', 'route' => 'teams.index', 'can' => 'users.view', 'soon' => true],
    ['label' => 'Roles y permisos', 'route' => 'roles.index', 'can' => 'users.view', 'soon' => true],
    ['label' => 'Configuración', 'route' => 'settings.index', 'can' => 'users.view', 'soon' => true],
];
$isActive = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*');
$canSee = function (array $item) use ($user): bool {
    if (isset($item['can_any'])) {
        return (bool) $user && $user->hasAnyPermission($item['can_any']);
    }

    return ! ($item['can'] ?? null) || ((bool) $user && $user->hasPermission($item['can']));
};
@endphp

{{-- Sidebar escritorio --}}
<aside class="hidden w-64 shrink-0 flex-col border-r border-slate-800 bg-slate-900 text-slate-200 lg:flex">
    <div class="flex h-16 items-center border-b border-slate-800 px-5">
        <span class="text-base font-semibold tracking-tight text-white">{{ config('app.name', 'NexusCRM') }}</span>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
        @foreach ($navSections as $section)
            @php $visible = collect($section['items'])->filter(fn ($i) => $canSee($i)); @endphp
            @if ($visible->isNotEmpty())
                <p class="px-2 pb-2 {{ $loop->first ? '' : 'pt-4 ' }}text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section['title'] }}</p>
                <ul class="space-y-1">
                    @foreach ($visible as $item)
                        <li>
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach
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
            @foreach ($navSections as $section)
                @php $visible = collect($section['items'])->filter(fn ($i) => $canSee($i)); @endphp
                @if ($visible->isNotEmpty())
                    <p class="px-2 pb-2 {{ $loop->first ? '' : 'pt-4 ' }}text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section['title'] }}</p>
                    <ul class="space-y-1">
                        @foreach ($visible as $item)
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 {{ $isActive($item['route']) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['soon'])<x-badge color="slate">Próx.</x-badge>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
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
