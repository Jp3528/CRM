@extends('layouts.app', ['header' => 'Convertir lead', 'subheader' => $lead->full_name])

@section('title', 'Convertir lead')

@section('breadcrumbs')
    <x-breadcrumbs :items="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Leads', 'url' => route('leads.index')], ['label' => $lead->full_name, 'url' => route('leads.show', $lead)], ['label' => 'Convertir']]" />
@endsection

@section('content')
    <x-card title="Convertir {{ $lead->full_name }}" subtitle="Operación atómica: todo se crea o nada. Requiere lead calificado.">
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('leads.convert.store', $lead) }}" class="grid gap-4 md:grid-cols-2">
            @csrf

            <div class="md:col-span-2 rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Lead: <strong>{{ $lead->full_name }}</strong>
                @if ($lead->email) · {{ $lead->email }}@endif
                @if ($lead->phone) · {{ $lead->phone }}@endif
                @if ($lead->company_name) · Empresa declarada: <strong>{{ $lead->company_name }}</strong>@endif
                · Estado: <strong>{{ $lead->status }}</strong>
            </div>

            <div x-data="{ mode: '{{ old('company_mode', 'new') }}' }">
                <x-label value="Empresa" />
                <label class="mr-4 text-sm"><input type="radio" name="company_mode" value="new" x-model="mode" class="mr-1">Crear nueva</label>
                <label class="text-sm"><input type="radio" name="company_mode" value="existing" x-model="mode" class="mr-1">Vincular existente</label>
                <div x-show="mode === 'new'" class="mt-2">
                    <x-label for="company_name" value="Nombre de la nueva empresa *" />
                    <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $lead->company_name) }}"
                        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <x-input-error :message="$errors->get('company_name')[0] ?? null" />
                </div>
                <div x-show="mode === 'existing'" class="mt-2" x-cloak>
                    <x-label for="company_id" value="Empresa existente *" />
                    <select id="company_id" name="company_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Seleccionar —</option>
                        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) old('company_id') === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
                    </select>
                    <x-input-error :message="$errors->get('company_id')[0] ?? null" />
                </div>
            </div>

            <div x-data="{ mode: '{{ old('contact_mode', 'new') }}' }">
                <x-label value="Contacto" />
                <label class="mr-4 text-sm"><input type="radio" name="contact_mode" value="new" x-model="mode" class="mr-1">Crear nuevo</label>
                <label class="text-sm"><input type="radio" name="contact_mode" value="existing" x-model="mode" class="mr-1">Vincular existente</label>
                <div x-show="mode === 'new'" class="mt-2 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    Se creará con los datos del lead (nombre, email, teléfono). El email del lead corresponde a la persona, no a la empresa.
                </div>
                <div x-show="mode === 'existing'" class="mt-2" x-cloak>
                    <x-label for="contact_id" value="Contacto existente *" />
                    <select id="contact_id" name="contact_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Seleccionar —</option>
                        @foreach ($contacts as $c)<option value="{{ $c->id }}" @selected((string) old('contact_id') === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}@if ($c->company) ({{ $c->company->trade_name }})@endif</option>@endforeach
                    </select>
                    <x-input-error :message="$errors->get('contact_id')[0] ?? null" />
                    <p class="mt-1 text-xs text-slate-500">Debe pertenecer a la empresa elegida (o no tener empresa, caso en que se vinculará).</p>
                </div>
            </div>

            <div class="md:col-span-2 border-t border-slate-100 pt-4" x-data="{ createOpp: {{ old('create_opportunity') ? 'true' : 'false' }} }">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="create_opportunity" value="1" x-model="createOpp" class="rounded border-slate-300">
                    Crear oportunidad (dato backend; pipeline Ventas, etapa Prospecto)
                </label>
                <div x-show="createOpp" class="mt-3 grid gap-4 md:grid-cols-3" x-cloak>
                    <div>
                        <x-label for="opportunity_name" value="Nombre oportunidad *" />
                        <input id="opportunity_name" name="opportunity_name" type="text" value="{{ old('opportunity_name', $suggestedOpportunityName) }}"
                            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <x-input-error :message="$errors->get('opportunity_name')[0] ?? null" />
                    </div>
                    <div>
                        <x-label for="opportunity_amount" value="Monto" />
                        <input id="opportunity_amount" name="opportunity_amount" type="number" step="0.01" min="0" value="{{ old('opportunity_amount', $lead->estimated_value) }}"
                            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <x-input-error :message="$errors->get('opportunity_amount')[0] ?? null" />
                    </div>
                    <div>
                        <x-label for="expected_close_date" value="Cierre esperado" />
                        <input id="expected_close_date" name="expected_close_date" type="date" value="{{ old('expected_close_date') }}"
                            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                        <x-input-error :message="$errors->get('expected_close_date')[0] ?? null" />
                    </div>
                </div>
            </div>

            <div>
                <x-label for="owner_id" value="Responsable de los registros creados" />
                <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Mantener responsable del lead ({{ $lead->owner?->name ?? 'ninguno' }}) —</option>
                    @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id') === (string) $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 md:col-span-2">
                <x-button>Convertir lead</x-button>
                <a href="{{ route('leads.show', $lead) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            </div>
        </form>
    </x-card>
@endsection
