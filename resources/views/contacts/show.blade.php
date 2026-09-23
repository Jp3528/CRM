@extends('layouts.app', ['header' => $contact->full_name, 'subheader' => 'Ficha de contacto'])

@section('title', $contact->full_name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Contactos', 'url' => route('contacts.index')], ['label' => $contact->full_name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-status-badge :status="$contact->status" />
        @foreach ($contact->tags as $tag)
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 ring-1 ring-inset ring-slate-200">
                {{ $tag->name }}
                @if ($canUpdate)
                    <form method="POST" action="{{ route('contacts.tags.detach', [$contact, $tag]) }}" class="inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-slate-400 hover:text-red-600" title="Quitar">×</button>
                    </form>
                @endif
            </span>
        @endforeach
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('contacts.edit', $contact) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('create', App\Models\Task::class)<a href="{{ route('tasks.create', ['related' => 'contact:'.$contact->id]) }}" class="text-slate-700 hover:underline">Nueva tarea</a>@endcan
            @can('create', App\Models\Activity::class)<a href="{{ route('activities.create', ['related' => 'contact:'.$contact->id]) }}" class="text-slate-700 hover:underline">Registrar actividad</a>@endcan
            @can('delete', $contact)
                <form method="POST" action="{{ route('contacts.destroy', $contact) }}" class="inline"
                    x-data @submit.prevent="if (confirm('¿Eliminar a {{ $contact->full_name }}?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Datos personales">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ $contact->full_name }}</dd></div>
                <div><dt class="text-slate-500">Cargo</dt><dd class="font-medium">{{ $contact->job_title ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Departamento</dt><dd class="font-medium">{{ $contact->department ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Empresa</dt><dd class="font-medium">@if ($contact->company)<a href="{{ route('companies.show', $contact->company) }}" class="hover:underline">{{ $contact->company->trade_name }}</a>@else — @endif</dd></div>
            </dl>
        </x-card>

        <x-card title="Contacto y responsable">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $contact->email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Teléfono</dt><dd class="font-medium">{{ $contact->phone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Móvil</dt><dd class="font-medium">{{ $contact->mobile ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $contact->owner?->name ?? '—' }}</dd></div>
            </dl>
            @if ($contact->notes)
                <div class="mt-3 border-t border-slate-100 pt-3 text-sm"><p class="text-slate-500">Notas</p><p class="mt-1 whitespace-pre-line">{{ $contact->notes }}</p></div>
            @endif
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creado {{ $contact->created_at->format('Y-m-d H:i') }} · Actualizado {{ $contact->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>
    </div>

    @include('partials.timeline', ['subject' => $contact])

    @if ($canViewQuotes && $contact->quotes->isNotEmpty())
        <x-card title="Cotizaciones recientes" subtitle="Documentos del contacto">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($contact->quotes as $quote)
                    <li class="flex items-center gap-2 py-2">
                        <a href="{{ route('quotes.show', $quote) }}" class="font-mono font-medium hover:underline">{{ $quote->number }}</a>
                        <x-status-badge :status="$quote->status" />
                        <span class="ml-auto text-slate-600">{{ number_format($quote->total, 2) }} {{ $quote->currency }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($canViewInvoices && $contact->invoices->isNotEmpty())
        <x-card title="Facturas recientes" subtitle="Documentos internos del contacto">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($contact->invoices as $invoice)
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
