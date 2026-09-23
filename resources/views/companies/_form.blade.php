@php $c = $company ?? new App\Models\Company; @endphp

<div>
    <x-label for="trade_name" value="Nombre comercial *" />
    <input id="trade_name" name="trade_name" type="text" required value="{{ old('trade_name', $c->trade_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('trade_name')[0] ?? null" />
</div>
<div>
    <x-label for="legal_name" value="Razón social" />
    <input id="legal_name" name="legal_name" type="text" value="{{ old('legal_name', $c->legal_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('legal_name')[0] ?? null" />
</div>
<div>
    <x-label for="tax_id" value="Identificación fiscal" />
    <input id="tax_id" name="tax_id" type="text" value="{{ old('tax_id', $c->tax_id) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('tax_id')[0] ?? null" />
</div>
<div>
    <x-label for="email" value="Email" />
    <input id="email" name="email" type="email" value="{{ old('email', $c->email) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('email')[0] ?? null" />
</div>
<div>
    <x-label for="phone" value="Teléfono" />
    <input id="phone" name="phone" type="text" value="{{ old('phone', $c->phone) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('phone')[0] ?? null" />
</div>
<div>
    <x-label for="website" value="Sitio web" />
    <input id="website" name="website" type="url" placeholder="https://…" value="{{ old('website', $c->website) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('website')[0] ?? null" />
</div>
<div>
    <x-label for="industry" value="Industria" />
    <input id="industry" name="industry" type="text" value="{{ old('industry', $c->industry) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="company_size" value="Tamaño" />
    <input id="company_size" name="company_size" type="text" placeholder="1-10, 11-50…" value="{{ old('company_size', $c->company_size) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="address" value="Dirección" />
    <input id="address" name="address" type="text" value="{{ old('address', $c->address) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="city" value="Ciudad" />
    <input id="city" name="city" type="text" value="{{ old('city', $c->city) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="region" value="Región" />
    <input id="region" name="region" type="text" value="{{ old('region', $c->region) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="country" value="País" />
    <input id="country" name="country" type="text" value="{{ old('country', $c->country) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="postal_code" value="Código postal" />
    <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $c->postal_code) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $c->status ?? 'active') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('status')[0] ?? null" />
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((string) old('owner_id', $c->owner_id) === (string) $o->id)>{{ $o->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('owner_id')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="notes" value="Notas" />
    <textarea id="notes" name="notes" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('notes', $c->notes) }}</textarea>
</div>
<div class="md:col-span-2">
    <x-label value="Etiquetas" />
    <div class="flex flex-wrap gap-2">
        @foreach ($allTags as $tag)
            <label class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                    @checked(in_array($tag->id, old('tags', $c->tags->pluck('id')->all() ?? []))) class="rounded border-slate-300">
                {{ $tag->name }}
            </label>
        @endforeach
        @if ($allTags->isEmpty())<span class="text-xs text-slate-400">Aún no hay etiquetas creadas.</span>@endif
    </div>
    <div class="mt-2">
        <x-label for="new_tags" value="Nuevas etiquetas (separadas por comas)" />
        <input id="new_tags" name="new_tags" type="text" value="{{ old('new_tags') }}" placeholder="cliente vip, Bogotá…"
            class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
        <x-input-error :message="$errors->get('new_tags')[0] ?? null" />
    </div>
</div>
