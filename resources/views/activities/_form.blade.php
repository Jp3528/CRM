@php $a = $activity ?? new App\Models\Activity; $isEdit = isset($activity); @endphp

<div>
    <x-label for="type" value="Tipo *" />
    <select id="type" name="type" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach ($types as $t)<option value="{{ $t }}" @selected(old('type', $a->type ?? $preselectedType ?? 'note') === $t)>{{ ucfirst($t) }}</option>@endforeach
    </select>
    <x-input-error :message="$errors->get('type')[0] ?? null" />
</div>
<div>
    <x-label for="subject" value="Título *" />
    <input id="subject" name="subject" type="text" required value="{{ old('subject', $a->subject) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">
    <x-input-error :message="$errors->get('subject')[0] ?? null" />
</div>
<div>
    <x-label for="status" value="Estado *" />
    <select id="status" name="status" required class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
        @foreach (\App\Models\Activity::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $a->status ?? 'pending') === $s)>{{ $s === 'pending' ? 'Pendiente' : 'Completada' }}</option>@endforeach
    </select>
</div>
<div>
    <x-label for="scheduled_at" value="Fecha programada (requerida si es reunión)" />
    <input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at', $a->scheduled_at?->format('Y-m-d\TH:i')) }}"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm">
    <x-input-error :message="$errors->get('scheduled_at')[0] ?? null" />
</div>
@if (! $isEdit)
    <div class="md:col-span-2">
        <x-label value="Entidad relacionada" />
        <div class="grid gap-2 sm:grid-cols-2">
            <select id="related_type" name="related_type" class="rounded-md border-slate-300 px-3 py-2 text-sm">
                <option value="">— Sin entidad —</option>
                @foreach (\App\Support\RelatedEntity::keys() as $type)<option value="{{ $type }}" @selected(old('related_type', $preselected['type'] ?? null) === $type)>{{ ucfirst($type) }}</option>@endforeach
            </select>
            <input id="related_id" name="related_id" type="number" min="1" placeholder="ID de la entidad"
                value="{{ old('related_id', $preselected['id'] ?? '') }}"
                class="rounded-md border-slate-300 px-3 py-2 text-sm">
        </div>
        @if (! empty($preselected['label']))
            <p class="mt-1 text-xs text-slate-500">Preseleccionado desde la ficha: <strong>{{ $preselected['label'] }}</strong></p>
        @endif
        <x-input-error :message="$errors->get('related_id')[0] ?? null" />
    </div>
@endif
<div class="md:col-span-2">
    <x-label for="description" value="Descripción" />
    <textarea id="description" name="description" rows="3"
        class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500">{{ old('description', $a->description) }}</textarea>
</div>
