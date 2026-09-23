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

    <x-card title="Actividad reciente" subtitle="Últimos registros relacionados">
        @if ($contact->activities->isEmpty())
            <p class="text-sm text-slate-500">Sin actividad registrada.</p>
        @else
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($contact->activities as $activity)
                    <li class="py-2">
                        <p class="font-medium">{{ $activity->subject ?? $activity->type }}</p>
                        @if ($activity->description)<p class="text-slate-600">{{ $activity->description }}</p>@endif
                        <p class="mt-0.5 text-xs text-slate-400">{{ $activity->type }} · {{ $activity->user?->name ?? '—' }} · {{ $activity->created_at->format('Y-m-d H:i') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
