@php $t = $template ?? new App\Models\MessageTemplate; @endphp

<div class="md:col-span-2">
    <x-label for="name" value="Nombre *" />
    <input id="name" name="name" type="text" required value="{{ old('name', $t->name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('name')[0] ?? null" />
</div>
<div>
    <x-label for="channel" value="Canal *" />
    <select id="channel" name="channel" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($channels as $c)<option value="{{ $c }}" @selected(old('channel', $t->channel ?? 'email') === $c)>{{ ucfirst($c) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $t->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="owner_id" value="Responsable" />
    <select id="owner_id" name="owner_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) old('owner_id', $t->owner_id) === (string) $u->id)>{{ $u->name }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="subject" value="Asunto (email)" />
    <input id="subject" name="subject" type="text" value="{{ old('subject', $t->subject) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
</div>
<div class="md:col-span-2">
    <x-label for="body" value="Cuerpo *" />
    <textarea id="body" name="body" rows="6" required
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">{{ old('body', $t->body) }}</textarea>
    <p class="mt-1 text-xs text-slate-400">Variables permitidas: first_name, last_name, full_name, company_name, email. Texto plano, sin HTML ni codigo.</p>
    <x-input-error :message="$errors->get('body')[0] ?? null" />
</div>
