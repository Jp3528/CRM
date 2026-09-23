@extends('layouts.app', ['header' => 'Dashboard', 'subheader' => 'Resumen de tu sesión'])

@section('title', 'Dashboard')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard']]" />
@endsection

@section('content')
    <x-card title="Hola, {{ $user->name }}" subtitle="{{ $now->format('l, d M Y · H:i') }}">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-slate-500">Rol principal</dt>
                <dd class="mt-1 font-medium">{{ $primaryRole?->name ?? 'Sin rol' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Equipo</dt>
                <dd class="mt-1 font-medium">{{ $user->team?->name ?? 'Sin equipo' }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Correo</dt>
                <dd class="mt-1 font-medium">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-slate-500">Estado</dt>
                <dd class="mt-1"><x-badge color="{{ $user->isActive() ? 'green' : 'red' }}">{{ $user->status }}</x-badge></dd>
            </div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($user->roles as $role)<x-badge>{{ $role->name }}</x-badge>@endforeach
        </div>
    </x-card>

    <div class="grid gap-4 md:grid-cols-2">
        <x-card title="Accesos rápidos" subtitle="Solo lo que tus permisos permiten">
            <ul class="space-y-2 text-sm">
                <li><a class="text-slate-700 hover:underline" href="{{ route('dashboard') }}">· Dashboard</a></li>
                @can('companies.view')<li><a class="text-slate-700 hover:underline" href="{{ route('companies.index') }}">· Empresas</a></li>@endcan
                @can('contacts.view')<li><a class="text-slate-700 hover:underline" href="{{ route('contacts.index') }}">· Contactos</a></li>@endcan
                @can('leads.view')<li><a class="text-slate-700 hover:underline" href="{{ route('leads.index') }}">· Leads</a></li>@endcan
                @can('opportunities.view')<li><a class="text-slate-700 hover:underline" href="{{ route('opportunities.index') }}">· Oportunidades</a></li>@endcan
                @can('tasks.view')<li><a class="text-slate-700 hover:underline" href="{{ route('tasks.index') }}">· Tareas</a></li>@endcan
                @can('activities.view')<li><a class="text-slate-700 hover:underline" href="{{ route('activities.index') }}">· Actividades</a></li>@endcan
                @if ($canSeeTasks || $canSeeActivities)<li><a class="text-slate-700 hover:underline" href="{{ route('calendar.index') }}">· Calendario</a></li>@endif
                @can('products.view')<li><a class="text-slate-700 hover:underline" href="{{ route('products.index') }}">· Productos</a></li>@endcan
                @can('quotes.view')<li><a class="text-slate-700 hover:underline" href="{{ route('quotes.index') }}">· Cotizaciones</a></li>@endcan
                @can('sales.view')<li><a class="text-slate-700 hover:underline" href="{{ route('sales.index') }}">· Ventas</a></li>@endcan
                @can('invoices.view')<li><a class="text-slate-700 hover:underline" href="{{ route('invoices.index') }}">· Facturas</a></li>@endcan
                @can('users.view')<li><a class="text-slate-700 hover:underline" href="{{ route('admin.users.index') }}">· Usuarios</a></li>@endcan
                <li><a class="text-slate-700 hover:underline" href="{{ route('profile.edit') }}">· Mi perfil</a></li>
            </ul>
        </x-card>

        <x-card title="Estado del sistema" subtitle="Base operativa de Fase 2">
            <ul class="space-y-2 text-sm text-slate-600">
                <li>· Autenticación web activa con regeneración de sesión.</li>
                <li>· RBAC operativo (roles, permisos directos, Superadministrador).</li>
                <li>· Autorización backend en rutas y Gates; el sidebar solo oculta enlaces.</li>
                <li>· Módulos comerciales pendientes (ver placeholders).</li>
            </ul>
        </x-card>
    </div>

    @if ($canSeeTasks || $canSeeActivities)
        <div class="grid gap-4 md:grid-cols-2">
            @if ($canSeeTasks)
                <x-card title="Mis tareas de hoy ({{ $tasksToday->count() }})" subtitle="Vencen hoy y siguen abiertas">
                    @if ($tasksToday->isEmpty())
                        <p class="text-sm text-slate-500">Nada vence hoy. <a href="{{ route('tasks.index') }}" class="hover:underline">Ver tareas</a></p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($tasksToday as $task)
                                <li><a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                                    <span class="text-xs text-slate-400">· {{ $task->related_label }} · {{ $task->due_at?->format('H:i') ?? '' }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
                <x-card title="Vencidas ({{ $overdueTasks->count() }}) + Próximas ({{ $upcomingTasks->count() }})" subtitle="Seguimiento personal">
                    @if ($overdueTasks->isNotEmpty())
                        <p class="mb-1 text-xs font-semibold uppercase text-red-600">Vencidas</p>
                        <ul class="mb-3 space-y-1 text-sm">
                            @foreach ($overdueTasks as $task)
                                <li><a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                                    <span class="text-xs text-slate-400">· venció {{ $task->due_at?->format('Y-m-d') }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($upcomingTasks->isNotEmpty())
                        <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Próximos 7 días</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($upcomingTasks as $task)
                                <li><a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                                    <span class="text-xs text-slate-400">· {{ $task->due_at?->format('Y-m-d') }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($overdueTasks->isEmpty() && $upcomingTasks->isEmpty())
                        <p class="text-sm text-slate-500">Sin pendientes próximos.</p>
                    @endif
                </x-card>
            @endif
            @if ($canSeeActivities)
                <x-card title="Próximas reuniones ({{ $upcomingMeetings->count() }})" subtitle="Programadas los próximos 7 días">
                    @if ($upcomingMeetings->isEmpty())
                        <p class="text-sm text-slate-500">Sin reuniones programadas.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($upcomingMeetings as $meeting)
                                <li><a href="{{ route('activities.show', $meeting) }}" class="hover:underline">{{ $meeting->subject ?? 'Reunión' }}</a>
                                    <span class="text-xs text-slate-400">· {{ $meeting->scheduled_at?->format('Y-m-d H:i') }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif
        </div>
    @endif

    @if ($canSeeQuotes)
        <div class="grid gap-4 md:grid-cols-2">
            <x-card title="Cotizaciones pendientes ({{ $pendingQuotes->count() }})" subtitle="Borrador o enviadas">
                @if ($pendingQuotes->isEmpty())
                    <p class="text-sm text-slate-500">Sin pendientes. <a href="{{ route('quotes.index') }}" class="hover:underline">Ver cotizaciones</a></p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($pendingQuotes as $quote)
                            <li><a href="{{ route('quotes.show', $quote) }}" class="font-mono hover:underline">{{ $quote->number }}</a>
                                <span class="text-xs text-slate-400">· {{ $quote->company?->trade_name ?? '—' }} · {{ number_format($quote->total, 2) }} {{ $quote->currency }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card title="Próximas a vencer ({{ $expiringQuotes->count() }})" subtitle="Vigencia dentro de 7 días">
                @if ($expiringQuotes->isEmpty())
                    <p class="text-sm text-slate-500">Ninguna por vencer.</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($expiringQuotes as $quote)
                            <li><a href="{{ route('quotes.show', $quote) }}" class="font-mono hover:underline">{{ $quote->number }}</a>
                                <span class="text-xs text-slate-400">· vence {{ $quote->valid_until?->format('Y-m-d') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif

    @if ($canSeeSales || $canSeeInvoices)
        <div class="grid gap-4 md:grid-cols-2">
            @if ($canSeeSales)
                <x-card title="Ventas recientes ({{ $recentSales->count() }})" subtitle="Últimos documentos">
                    @if ($recentSales->isEmpty())
                        <p class="text-sm text-slate-500">Sin ventas. <a href="{{ route('sales.index') }}" class="hover:underline">Ver ventas</a></p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($recentSales as $sale)
                                <li><a href="{{ route('sales.show', $sale) }}" class="font-mono hover:underline">{{ $sale->number }}</a>
                                    <span class="text-xs text-slate-400">· {{ $sale->company?->trade_name ?? '—' }} · {{ number_format($sale->total, 2) }} {{ $sale->currency }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif
            @if ($canSeeInvoices)
                <x-card title="Facturas pendientes ({{ $pendingInvoices->count() }}) + vencidas ({{ $overdueInvoices->count() }})" subtitle="Documentos internos">
                    @if ($pendingInvoices->isNotEmpty())
                        <ul class="mb-2 space-y-1 text-sm">
                            @foreach ($pendingInvoices as $invoice)
                                <li><a href="{{ route('invoices.show', $invoice) }}" class="font-mono hover:underline">{{ $invoice->number }}</a>
                                    <span class="text-xs text-slate-400">· {{ $invoice->status }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($overdueInvoices->isNotEmpty())
                        <p class="mb-1 text-xs font-semibold uppercase text-yellow-700">Vencidas</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($overdueInvoices as $invoice)
                                <li><a href="{{ route('invoices.show', $invoice) }}" class="font-mono hover:underline">{{ $invoice->number }}</a>
                                    <span class="text-xs text-slate-400">· venció {{ $invoice->due_date?->format('Y-m-d') }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($pendingInvoices->isEmpty() && $overdueInvoices->isEmpty())
                        <p class="text-sm text-slate-500">Sin pendientes.</p>
                    @endif
                </x-card>
            @endif
        </div>
    @endif
@endsection
