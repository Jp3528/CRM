@php $c = $campaign ?? new App\Models\Campaign; @endphp

<div class="md:col-span-2">
    <x-label for="name" value="Nombre *" />
    <input id="name" name="name" type="text" required value="{{ old('name', $c->name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('name')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripción" />
    <textarea id="description" name="description" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('description', $c->description) }}</textarea>
</div>
<div>
    <x-label for="type" value="Tipo *" />
    <select id="type" name="type" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($types as $t)<option value="{{ $t }}" @selected(old('type', $c->type ?? 'other') === $t)>{{ ucfirst($t) }}</option>@endforeach
    </select>
    <p class="mt-1 text-xs text-slate-400">email/sms/whatsapp son solo clasificación: no se envía nada externo.</p>
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $c->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('status')[0] ?? null" />
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) old('owner_id', $c->owner_id) === (string) $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>
<div>
    <x-label for="start_at" value="Inicio" />
    <input id="start_at" name="start_at" type="date" value="{{ old('start_at', $c->start_at?->format('Y-m-d')) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
<div>
    <x-label for="end_at" value="Fin" />
    <input id="end_at" name="end_at" type="date" value="{{ old('end_at', $c->end_at?->format('Y-m-d')) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('end_at')[0] ?? null" />
</div>
<div>
    <x-label for="budget" value="Presupuesto" />
    <input id="budget" name="budget" type="number" step="0.01" min="0" value="{{ old('budget', $c->budget) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
<div>
    <x-label for="expected_revenue" value="Ingreso esperado" />
    <input id="expected_revenue" name="expected_revenue" type="number" step="0.01" min="0" value="{{ old('expected_revenue', $c->expected_revenue) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
<div>
    <x-label for="actual_cost" value="Costo real" />
    <input id="actual_cost" name="actual_cost" type="number" step="0.01" min="0" value="{{ old('actual_cost', $c->actual_cost) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
