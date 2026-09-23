@extends('layouts.app', ['header' => 'Nueva comunicación', 'subheader' => 'Registro interno, sin envío externo'])

@section('title', 'Nueva comunicación')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Comunicaciones', 'url' => route('communications.index')], ['label' => 'Nueva']]" />
@endsection

@section('content')
    <x-card title="Redactar comunicación" subtitle="Se guarda como borrador; luego registra el envio simulado">
        <form method="POST" action="{{ route('communications.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <x-label for="channel" value="Canal *" />
                <select id="channel" name="channel" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    @foreach ($channels as $c)<option value="{{ $c }}" @selected(old('channel', 'email') === $c)>{{ ucfirst($c) }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-label for="template_id" value="Plantilla (opcional)" />
                <select id="template_id" name="template_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Sin plantilla —</option>
                    @foreach ($templates as $t)<option value="{{ $t->id }}" @selected((string) old('template_id', $preselectedTemplateId) === (string) $t->id)>{{ $t->name }} ({{ $t->channel }})</option>@endforeach
                </select>
            </div>
            <div>
                <x-label for="campaign_id" value="Campaña (opcional)" />
                <select id="campaign_id" name="campaign_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Sin campaña —</option>
                    @foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected((string) old('campaign_id', $preselectedCampaignId) === (string) $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-label for="owner_id" value="Responsable" />
                <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Yo —</option>
                    @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) old('owner_id') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-label for="contact_id" value="Contacto objetivo" />
                <select id="contact_id" name="contact_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Ninguno —</option>
                    @foreach ($contacts as $c)<option value="{{ $c->id }}" @selected((string) old('contact_id', $preselectedContactId) === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>@endforeach
                </select>
                <x-input-error :message="$errors->get('contact_id')[0] ?? null" />
            </div>
            <div>
                <x-label for="lead_id" value="Lead objetivo" />
                <select id="lead_id" name="lead_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Ninguno —</option>
                    @foreach ($leads as $l)<option value="{{ $l->id }}" @selected((string) old('lead_id', $preselectedLeadId) === (string) $l->id)>{{ $l->first_name }} {{ $l->last_name }}</option>@endforeach
                </select>
                <x-input-error :message="$errors->get('lead_id')[0] ?? null" />
            </div>
            <div class="md:col-span-2">
                <x-label for="subject" value="Asunto" />
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <x-label for="body" value="Mensaje *" />
                <textarea id="body" name="body" rows="6" required
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('body') }}</textarea>
                <p class="mt-1 text-xs text-slate-400">Variables: first_name, last_name, full_name, company_name, email. Solo se sustituyen estas; sin HTML ni codigo.</p>
                <x-input-error :message="$errors->get('body')[0] ?? null" />
            </div>
            <div class="flex gap-2 md:col-span-2">
                <x-button>Guardar borrador</x-button>
                <a href="{{ route('communications.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
