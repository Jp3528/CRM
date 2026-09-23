@extends('layouts.app', ['header' => $template->name, 'subheader' => 'Ficha de plantilla'])

@section('title', $template->name)

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Plantillas', 'url' => route('templates.index')], ['label' => $template->name]]" />
@endsection

@section('content')
    <div class="flex flex-wrap items-center gap-2">
        <x-badge>{{ ucfirst($template->channel) }}</x-badge>
        <x-status-badge :status="$template->status" :label="ucfirst($template->status)" />
        <span class="ml-auto flex gap-2 text-sm">
            @if ($canUpdate)<a href="{{ route('templates.edit', $template) }}" class="text-slate-700 hover:underline">Editar</a>@endif
            @can('delete', $template)
                <form method="POST" action="{{ route('templates.destroy', $template) }}" class="inline"
                    x-data @submit.prevent="if (confirm('Eliminar plantilla?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                </form>
            @endcan
        </span>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Contenido (escapado)">
            <dl class="grid gap-3 text-sm">
                <div><dt class="text-slate-500">Asunto</dt><dd class="font-medium">{{ $template->subject ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Cuerpo</dt><dd class="mt-1 whitespace-pre-line rounded-md border border-slate-200 bg-slate-50 px-3 py-2">{{ $template->body }}</dd></div>
                <div><dt class="text-slate-500">Responsable</dt><dd class="font-medium">{{ $template->owner?->name ?? '—' }}</dd></div>
            </dl>
            <div class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">
                Creada {{ $template->created_at->format('Y-m-d H:i') }} · Actualizada {{ $template->updated_at->format('Y-m-d H:i') }}
            </div>
        </x-card>

        <x-card title="Vista previa segura" subtitle="Datos de ejemplo, sin ejecutar codigo">
            <p class="whitespace-pre-line rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">{{ $preview }}</p>
            <p class="mt-2 text-xs text-slate-400">Variables: first_name, last_name, full_name, company_name, email. Desconocidas se ignoran de forma segura.</p>
        </x-card>
    </div>
@endsection
