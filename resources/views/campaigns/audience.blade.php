@extends('layouts.app', ['header' => 'Audiencia: '.$campaign->name, 'subheader' => 'Constructor básico con alcance'])

@section('title', 'Audiencia')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Campañas', 'url' => route('campaigns.index')], ['label' => $campaign->name, 'url' => route('campaigns.show', $campaign)], ['label' => 'Audiencia']]" />
@endsection

@section('content')
    <x-card title="Audiencia" subtitle="Contactos: {{ $contactPreview }} · Leads: {{ $leadPreview }} (solo tu alcance)">
        <div class="mb-3 flex gap-2 text-sm">
            <a href="{{ route('campaigns.audience', [$campaign, 'tab' => 'contacts']) }}" class="rounded-md px-3 py-1 {{ $tab === 'contacts' ? 'bg-slate-900 text-white' : 'border border-slate-300' }}">Contactos</a>
            <a href="{{ route('campaigns.audience', [$campaign, 'tab' => 'leads']) }}" class="rounded-md px-3 py-1 {{ $tab === 'leads' ? 'bg-slate-900 text-white' : 'border border-slate-300' }}">Leads</a>
        </div>

        @if ($tab === 'contacts')
            <form method="GET" action="{{ route('campaigns.audience', $campaign) }}" class="mb-4 grid gap-2 md:grid-cols-4">
                <input type="hidden" name="tab" value="contacts">
                <select name="contact_status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    @foreach ($contactStatuses as $s)<option value="{{ $s }}" @selected(($filters['contact_status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <select name="contact_owner" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todos los responsables</option>
                    @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) ($filters['contact_owner'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                </select>
                <select name="contact_company" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todas las empresas</option>
                    @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) ($filters['contact_company'] ?? '') === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
                </select>
                <select name="contact_tag" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todas las etiquetas</option>
                    @foreach ($tags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['contact_tag'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <div class="flex gap-2 md:col-span-4">
                    <x-button>Vista previa</x-button>
                    @if ($canManage)
                        <button type="submit" form="bulk-contacts" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Agregar {{ $contactPreview }} a la campaña</button>
                    @endif
                </div>
            </form>
            @if ($canManage)
                <form id="bulk-contacts" method="POST" action="{{ route('campaigns.members.bulk', $campaign) }}">
                    @csrf
                    <input type="hidden" name="tab" value="contacts">
                    <input type="hidden" name="contact_status" value="{{ $filters['contact_status'] ?? '' }}">
                    <input type="hidden" name="contact_owner" value="{{ $filters['contact_owner'] ?? '' }}">
                    <input type="hidden" name="contact_company" value="{{ $filters['contact_company'] ?? '' }}">
                    <input type="hidden" name="contact_tag" value="{{ $filters['contact_tag'] ?? '' }}">
                </form>
            @endif
        @else
            <form method="GET" action="{{ route('campaigns.audience', $campaign) }}" class="mb-4 grid gap-2 md:grid-cols-4">
                <input type="hidden" name="tab" value="leads">
                <select name="lead_status" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    @foreach ($leadStatuses as $s)<option value="{{ $s }}" @selected(($filters['lead_status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <select name="lead_source" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todos los orígenes</option>
                    @foreach ($leadSources as $s)<option value="{{ $s }}" @selected(($filters['lead_source'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <input type="number" name="lead_score_min" min="0" max="100" placeholder="Score mínimo" value="{{ $filters['lead_score_min'] ?? '' }}" class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <select name="lead_tag" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                    <option value="">Todas las etiquetas</option>
                    @foreach ($tags as $t)<option value="{{ $t->id }}" @selected((string) ($filters['lead_tag'] ?? '') === (string) $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <div class="flex gap-2 md:col-span-4">
                    <x-button>Vista previa</x-button>
                    @if ($canManage)
                        <button type="submit" form="bulk-leads" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Agregar {{ $leadPreview }} a la campaña</button>
                    @endif
                </div>
            </form>
            @if ($canManage)
                <form id="bulk-leads" method="POST" action="{{ route('campaigns.members.bulk', $campaign) }}">
                    @csrf
                    <input type="hidden" name="tab" value="leads">
                    <input type="hidden" name="lead_status" value="{{ $filters['lead_status'] ?? '' }}">
                    <input type="hidden" name="lead_source" value="{{ $filters['lead_source'] ?? '' }}">
                    <input type="hidden" name="lead_score_min" value="{{ $filters['lead_score_min'] ?? '' }}">
                    <input type="hidden" name="lead_tag" value="{{ $filters['lead_tag'] ?? '' }}">
                </form>
            @endif
        @endif

        <p class="text-xs text-slate-400">La vista previa y el alta usan tu DataScope. El backend revalida cada ID y rechaza más de 500 por operación.</p>
    </x-card>
@endsection
