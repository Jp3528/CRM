@props(['title' => '', 'subtitle' => null, 'rows' => [], 'max' => null])

@php
$values = array_values(array_map(fn ($r) => (float) ($r['value'] ?? 0), $rows));
$peak = $max ?? (count($values) ? max(max($values), 1) : 1);
@endphp

<x-card :title="$title" :subtitle="$subtitle">
    @if (empty($rows))
        <p class="text-sm text-slate-500">Sin datos para el período seleccionado.</p>
    @else
        <dl class="space-y-2">
            @foreach ($rows as $row)
                <div>
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <dt class="truncate text-slate-600">{{ $row['label'] }}</dt>
                        <dd class="shrink-0 font-medium text-slate-900">{{ $row['display'] ?? $row['value'] }}</dd>
                    </div>
                    <div class="mt-0.5 h-2 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $row['label'] }}: {{ $row['display'] ?? $row['value'] }}">
                        <div class="h-full rounded-full bg-slate-700" style="width: {{ $peak > 0 ? min(100, round((float) $row['value'] / $peak * 100, 1)) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </dl>
    @endif
</x-card>
