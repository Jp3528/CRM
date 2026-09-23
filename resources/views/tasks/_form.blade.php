@php $t = $task ?? new App\Models\Task; @endphp

<div class="md:col-span-2">
    <x-label for="title" value="Título *" />
    <input id="title" name="title" type="text" required value="{{ old('title', $t->title) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('title')[0] ?? null" />
</div>
<div class="md:col-span-2">
    <x-label for="description" value="Descripción" />
    <textarea id="description" name="description" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('description', $t->description) }}</textarea>
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(old('status', $t->status ?? 'pending') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="priority" value="Prioridad *" />
    <select id="priority" name="priority" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($priorities as $p)<option value="{{ $p }}" @selected(old('priority', $t->priority ?? 'medium') === $p)>{{ ucfirst($p) }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="due_at" value="Vencimiento" />
    <input id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at', $t->due_at?->format('Y-m-d\TH:i')) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
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
    <x-label value="Entidad relacionada" />
    <div class="grid gap-2 sm:grid-cols-2">
        <select id="related_type" name="related_type" class="rounded-md border-slate-300 px-3 py-2 text-sm">
            <option value="">— Sin entidad —</option>
            @foreach ($relatedTypes as $type)<option value="{{ $type }}" @selected(old('related_type', $preselected['type'] ?? $currentRelated['type'] ?? null) === $type)>{{ ucfirst($type) }}</option>@endforeach
        </select>
        <input id="related_id" name="related_id" type="number" min="1" placeholder="ID de la entidad"
            value="{{ old('related_id', $preselected['id'] ?? $currentRelated['id'] ?? '') }}"
            class="rounded-md border-slate-300 px-3 py-2 text-sm">
    </div>
    @if (! empty($preselected['label']))
        <p class="mt-1 text-xs text-slate-500">Preseleccionado desde la ficha: <strong>{{ $preselected['label'] }}</strong></p>
    @endif
    <x-input-error :message="$errors->get('related_id')[0] ?? null" />
    <x-input-error :message="$errors->get('related_type')[0] ?? null" />
</div>
