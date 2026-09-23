@php $t = $contact ?? new App\Models\Contact; @endphp

<div>
    <x-label for="first_name" value="Nombre *" />
    <input id="first_name" name="first_name" type="text" required value="{{ old('first_name', $t->first_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('first_name')[0] ?? null" />
</div>
<div>
    <x-label for="last_name" value="Apellido" />
    <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $t->last_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="company_id" value="Empresa" />
    <select id="company_id" name="company_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin empresa —</option>
        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $preselectedCompanyId ?? $t->company_id) === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('company_id')[0] ?? null" />
</div>
<div>
    <x-label for="email" value="Email" />
    <input id="email" name="email" type="email" value="{{ old('email', $t->email) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('email')[0] ?? null" />
</div>
<div>
    <x-label for="phone" value="Teléfono" />
    <input id="phone" name="phone" type="text" value="{{ old('phone', $t->phone) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="mobile" value="Móvil" />
    <input id="mobile" name="mobile" type="text" value="{{ old('mobile', $t->mobile) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="job_title" value="Cargo" />
    <input id="job_title" name="job_title" type="text" value="{{ old('job_title', $t->job_title) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="department" value="Departamento" />
    <input id="department" name="department" type="text" value="{{ old('department', $t->department) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $t->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('status')[0] ?? null" />
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id', $t->owner_id) === (string) $o->id)>{{ $o->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="notes" value="Notas" />
    <textarea id="notes" name="notes" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('notes', $t->notes) }}</textarea>
</div>
<div class="md:col-span-2">
    <x-label value="Etiquetas" />
    <div class="flex flex-wrap gap-2">
        @foreach ($allTags as $tag)
            <label class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                    @checked(in_array($tag->id, old('tags', $t->tags->pluck('id')->all() ?? []))) class="rounded border-slate-300">
                {{ $tag->name }}
            </label>
        @endforeach
        @if ($allTags->isEmpty())<span class="text-xs text-slate-400">Aún no hay etiquetas creadas.</span>@endif
    </div>
    <div class="mt-2">
        <x-label for="new_tags" value="Nuevas etiquetas (separadas por comas)" />
        <input id="new_tags" name="new_tags" type="text" value="{{ old('new_tags') }}" placeholder="decision-maker, Bogotá…"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    </div>
</div>
