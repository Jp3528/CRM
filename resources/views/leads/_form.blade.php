@php $l = $lead ?? new App\Models\Lead; @endphp

<div>
    <x-label for="first_name" value="Nombre *" />
    <input id="first_name" name="first_name" type="text" required value="{{ old('first_name', $l->first_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('first_name')[0] ?? null" />
</div>
<div>
    <x-label for="last_name" value="Apellido" />
    <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $l->last_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="company_name" value="Empresa declarada" />
    <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $l->company_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="email" value="Email" />
    <input id="email" name="email" type="email" value="{{ old('email', $l->email) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('email')[0] ?? null" />
</div>
<div>
    <x-label for="phone" value="Teléfono" />
    <input id="phone" name="phone" type="text" value="{{ old('phone', $l->phone) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="source" value="Origen" />
    <select id="source" name="source" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin especificar —</option>
        @foreach ($sources as $s)<option value="{{ $s }}" @selected(old('source', $l->source) === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('source')[0] ?? null" />
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $l->status ?? 'new') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('status')[0] ?? null" />
</div>
<div>
    <x-label for="score" value="Score (0–100) *" />
    <input id="score" name="score" type="number" required min="0" max="100" value="{{ old('score', $l->score ?? 0) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('score')[0] ?? null" />
</div>
<div>
    <x-label for="estimated_value" value="Valor estimado" />
    <input id="estimated_value" name="estimated_value" type="number" step="0.01" min="0" value="{{ old('estimated_value', $l->estimated_value) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('estimated_value')[0] ?? null" />
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id', $l->owner_id) === (string) $o->id)>{{ $o->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="notes" value="Notas" />
    <textarea id="notes" name="notes" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('notes', $l->notes) }}</textarea>
</div>
<div class="md:col-span-2">
    <x-label value="Etiquetas" />
    <div class="flex flex-wrap gap-2">
        @foreach ($allTags as $tag)
            <label class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                    @checked(in_array($tag->id, old('tags', $l->tags->pluck('id')->all() ?? []))) class="rounded border-slate-300">
                {{ $tag->name }}
            </label>
        @endforeach
        @if ($allTags->isEmpty())<span class="text-xs text-slate-400">Aún no hay etiquetas creadas.</span>@endif
    </div>
    <div class="mt-2">
        <x-label for="new_tags" value="Nuevas etiquetas (separadas por comas)" />
        <input id="new_tags" name="new_tags" type="text" value="{{ old('new_tags') }}" placeholder="feria, referido…"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    </div>
</div>
