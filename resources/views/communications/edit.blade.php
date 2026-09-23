@extends('layouts.app', ['header' => 'Editar comunicación', 'subheader' => 'Solo borradores'])

@section('title', 'Editar comunicación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Comunicaciones', 'url' => route('communications.index')], ['label' => $communication->subject ?? ('#'.$communication->id), 'url' => route('communications.show', $communication)], ['label' => 'Editar']]" />
@endsection

@section('content')
    <x-card title="Editar borrador">
        <form method="POST" action="{{ route('communications.update', $communication) }}" class="grid gap-4">
            @csrf @method('PUT')
            <div>
                <x-label for="subject" value="Asunto" />
                <input id="subject" name="subject" type="text" value="{{ old('subject', $communication->subject) }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <x-label for="body" value="Mensaje *" />
                <textarea id="body" name="body" rows="6" required
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('body', $communication->body) }}</textarea>
                <x-input-error :message="$errors->get('body')[0] ?? null" />
            </div>
            <div class="flex gap-2">
                <x-button>Guardar</x-button>
                <a href="{{ route('communications.show', $communication) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
