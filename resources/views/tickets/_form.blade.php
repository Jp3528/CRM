@php $t = $ticket ?? new App\Models\Ticket; @endphp

<div class="md:col-span-2">
    <x-label for="subject" value="Asunto *" />
    <input id="subject" name="subject" type="text" required value="{{ old('subject', $t->subject) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('subject')[0] ?? null" />
</div>
<div>
    <x-label for="company_id" value="Empresa" />
    <select id="company_id" name="company_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin empresa —</option>
        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) old('company_id', $preselectedCompanyId ?? $t->company_id) === (string) $c->id)>{{ $c->trade_name }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="contact_id" value="Contacto" />
    <select id="contact_id" name="contact_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin contacto —</option>
        @foreach ($contacts as $c)<option value="{{ $c->id }}" @selected((string) old('contact_id', $preselectedContactId ?? $t->contact_id) === (string) $c->id)>{{ $c->first_name }} {{ $c->last_name }}@if ($c->company) ({{ $c->company->trade_name }})@endif</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('contact_id')[0] ?? null" />
</div>
<div>
    <x-label for="requester_name" value="Solicitante (si no hay contacto)" />
    <input id="requester_name" name="requester_name" type="text" value="{{ old('requester_name', $t->requester_name) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('requester_name')[0] ?? null" />
</div>
<div>
    <x-label for="requester_email" value="Email solicitante" />
    <input id="requester_email" name="requester_email" type="email" value="{{ old('requester_email', $t->requester_email) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('requester_email')[0] ?? null" />
</div>
<div>
    <x-label for="category_id" value="Categoría" />
    <select id="category_id" name="category_id" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin categoría —</option>
        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected((string) old('category_id', $t->category_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="priority" value="Prioridad *" />
    <select id="priority" name="priority" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($priorities as $p)<option value="{{ $p }}" @selected(old('priority', $t->priority ?? 'medium') === $p)>{{ ucfirst($p) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="channel" value="Canal *" />
    <select id="channel" name="channel" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($channels as $c)<option value="{{ $c }}" @selected(old('channel', $t->channel ?? 'web') === $c)>{{ ucfirst($c) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="assigned_to" value="Asignado a" />
    <select id="assigned_to" name="assigned_to" class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        <option value="">— Sin asignar —</option>
        @foreach ($assignees as $u)<option value="{{ $u->id }}" @selected((string) old('assigned_to', $t->assigned_to) === (string) $u->id)>{{ $u->name }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('assigned_to')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripción inicial" />
    <textarea id="description" name="description" rows="4"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('description', $t->description) }}</textarea>
</div>
