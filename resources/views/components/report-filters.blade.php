@props(['action' => '', 'filters' => [], 'owners' => [], 'showOwner' => true])

<form method="GET" action="{{ $action }}" class="mb-4 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3">
    <div>
        <x-label for="preset" value="Período" />
        <select id="preset" name="preset" class="rounded-md border-slate-300 px-2 py-2 text-sm">
            @foreach (['today' => 'Hoy', '7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', 'month' => 'Este mes', 'prev_month' => 'Mes anterior', 'quarter' => 'Este trimestre', 'year' => 'Este año'] as $p => $label)
                <option value="{{ $p }}" @selected(($filters['preset'] ?? 'month') === $p)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-label for="date_from" value="Desde" />
        <input type="date" id="date_from" name="date_from" value="{{ $filters['preset'] === 'custom' ? $filters['date_from'] : '' }}"
            class="rounded-md border-slate-300 px-2 py-2 text-sm">
    </div>
    <div>
        <x-label for="date_to" value="Hasta" />
        <input type="date" id="date_to" name="date_to" value="{{ $filters['preset'] === 'custom' ? $filters['date_to'] : '' }}"
            class="rounded-md border-slate-300 px-2 py-2 text-sm">
    </div>
    @if ($showOwner)
        <div>
            <x-label for="owner_id" value="Responsable" />
            <select id="owner_id" name="owner_id" class="rounded-md border-slate-300 px-2 py-2 text-sm">
                <option value="">Todos (en mi alcance)</option>
                @foreach ($owners as $u)<option value="{{ $u->id }}" @selected((string) ($filters['owner_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
        </div>
    @endif
    <div class="flex gap-2">
        <x-button>Aplicar</x-button>
        <a href="{{ $action }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Limpiar</a>
    </div>
    @if ($errors->any())
        <div class="w-full">
            @foreach ($errors->all() as $error)<x-input-error :message="$error" />@endforeach
        </div>
    @endif
</form>
