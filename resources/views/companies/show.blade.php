@extends('layouts.app', ['header' => $company->trade_name, 'subheader' => 'Ficha de empresa'])

@section('title', $company->trade_name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Empresas', 'url' => route('companies.index')], ['label' => $company->trade_name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$company->status" />
        @foreach ($company->tags as $tag)
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                {{ $tag->name }}
                @if ($canUpdate)
                    <form method="POST" action="{{ route('companies.tags.detach', [$company, $tag]) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Quitar">×</button>
                    </form>
                @endif
            </span>
        @endforeach
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('companies.edit', $company) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['related' => 'company:'.$company->id]) }}" class="text-slate-700 hover:underline">Nueva tarea</a>@endcan
            @can('create', App\Models\Activity::class)<a href="{{ route('activities.create', ['related' => 'company:'.$company->id]) }}" class="text-slate-700 hover:underline">Registrar actividad</a>@endcan
            @can('create', App\Models\Ticket::class)<a href="{{ route('tickets.create', ['company_id' => $company->id]) }}" class="text-slate-700 hover:underline">Nuevo ticket</a>@endcan
            @can('delete', $company)
                <form method="POST" action="{{ route('companies.destroy', $company) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar {{ $company->trade_name }}? Los contactos asociados se conservan.')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos principales" subtitle="Información comercial">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Nombre comercial</dt><dd class="font-medium">{{ $company->trade_name }}</dd></div>
                <div><dt class="text-slate-500">Razón social</dt><dd class="font-medium">{{ $company->legal_name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">NIT / Tax ID</dt><dd class="font-medium">{{ $company->tax_id ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Industria</dt><dd class="font-medium">{{ $company->industry ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Tamaño</dt><dd class="font-medium">{{ $company->company_size ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Sitio web</dt><dd class="font-medium">@if ($company->website)<a href="{{ $company->website }}" target="_blank" class="text-slate-700 hover:underline">{{ $company->website }}</a>@else — @endif</dd></div>
            </dl>
        </x-card>

        <x-card title="Contacto y ubicación">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $company->email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Teléfono</dt><dd class="font-medium">{{ $company->phone ?? '—' }}</dd></div>
                <div class="col-span-2"><dt class="text-slate-500">Dirección</dt><dd class="font-medium">{{ collect([$company->address, $company->city, $company->region, $company->country, $company->postal_code])->filter()->join(', ') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $company->owner?->name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Estado</dt><dd><x-status-badge :status="$company->status" /></dd></div>
            </dl>
            @if ($company->notes)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $company->notes }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $company->created_at->format('Y-m-d H:i') }} · Actualizada {{ $company->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>
    </div>

    <x-card title="Contactos asociados ({{ $company->contacts_count }})" subtitle="Personas de esta empresa">
        @if ($company->contacts->isEmpty())
            <x-empty-state title="Sin contactos asociados." message="Asocia contactos a esta empresa para verlos aquí."
                :action-url="$canCreateContact ? route('contacts.create', ['company_id' => $company->id]) : null" action-label="Crear contacto" />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-4 py-2 text-left">Nombre</th><th class="px-4 py-2 text-left">Cargo</th><th class="px-4 py-2 text-left">Email</th><th class="px-4 py-2 text-left">Teléfono</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2 text-right">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($company->contacts as $contact)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium"><a href="{{ route('contacts.show', $contact) }}" class="hover:underline">{{ $contact->full_name }}</a></td>
                                <td class="px-4 py-2 text-slate-600">{{ $contact->job_title ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $contact->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $contact->phone ?? $contact->mobile ?? '—' }}</td>
                                <td class="px-4 py-2"><x-status-badge :status="$contact->status" /></td>
                                <td class="px-4 py-2 text-right"><a href="{{ route('contacts.show', $contact) }}" class="text-slate-600 hover:underline">Ver</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($canCreateContact)
                <div class="mt-3 text-sm"><a href="{{ route('contacts.create', ['company_id' => $company->id]) }}" class="text-slate-700 hover:underline">+ Crear contacto en esta empresa</a></div>
            @endif
        @endif
    </x-card>

    @include('partials.timeline', ['subject' => $company])

    @if ($canViewTickets)
        <x-card title="Tickets recientes" subtitle="Casos de soporte de la empresa">
            @if ($company->tickets->isEmpty())
                <p class="text-sm text-slate-500">Sin tickets.
                    @can('create', App\Models\Ticket::class)<a href="{{ route('tickets.create', ['company_id' => $company->id]) }}" class="hover:underline">Abrir el primero</a>@endcan
                </p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($company->tickets as $ticket)
                        <li class="flex items-center gap-2 py-2">
                            <a href="{{ route('tickets.show', $ticket) }}" class="font-mono font-medium hover:underline">{{ $ticket->number }}</a>
                            <span class="truncate">{{ $ticket->subject }}</span>
                            <span class="ml-auto"><x-status-badge :status="$ticket->status" :label="ucfirst($ticket->status)" /></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    @if ($canViewQuotes)
        <x-card title="Cotizaciones recientes" subtitle="Últimos documentos de la empresa">
            @if ($company->quotes->isEmpty())
                <p class="text-sm text-slate-500">Sin cotizaciones.</p>
            @else
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($company->quotes as $quote)
                        <li class="flex items-center gap-2 py-2">
                            <a href="{{ route('quotes.show', $quote) }}" class="font-mono font-medium hover:underline">{{ $quote->number }}</a>
                            <x-status-badge :status="$quote->status" />
                            <span class="ml-auto text-slate-600">{{ number_format($quote->total, 2) }} {{ $quote->currency }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    @if ($canViewSales && $company->sales->isNotEmpty())
        <x-card title="Ventas recientes" subtitle="Últimas ventas de la empresa">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($company->sales as $sale)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('sales.show', $sale) }}" class="font-mono font-medium hover:underline">{{ $sale->number }}</a>
                        <x-status-badge :status="$sale->status" />
                        <span class="ml-auto text-slate-600">{{ number_format($sale->total, 2) }} {{ $sale->currency }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($canViewInvoices && $company->invoices->isNotEmpty())
        <x-card title="Facturas recientes" subtitle="Documentos internos de la empresa">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($company->invoices as $invoice)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('invoices.show', $invoice) }}" class="font-mono font-medium hover:underline">{{ $invoice->number }}</a>
                        <x-status-badge :status="$invoice->status" />
                        <span class="ml-auto text-slate-600">{{ number_format($invoice->total, 2) }} {{ $invoice->currency }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
