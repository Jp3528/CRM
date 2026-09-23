@props(['label' => '', 'value' => '—', 'secondary' => null, 'trend' => null, 'href' => null, 'note' => null])

@php
$trendPositive = $trend !== null && str_starts_with($trend, '+');
$trendNegative = $trend !== null && str_starts_with($trend, '-');
@endphp

<div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-xl font-semibold text-slate-900">
        @if ($href)<a href="{{ $href }}" class="hover:underline">{{ $value }}</a>@else{{ $value }}@endif
    </p>
    @if ($secondary !== null)<p class="mt-0.5 text-xs text-slate-500">{{ $secondary }}</p>@endif
    @if ($trend !== null)
        <p class="mt-0.5 text-xs font-medium {{ $trendPositive ? 'text-green-700' : ($trendNegative ? 'text-red-700' : 'text-slate-500') }}">{{ $trend }} vs período anterior</p>
    @endif
    @if ($note !== null)<p class="mt-0.5 text-[11px] text-slate-400">{{ $note }}</p>@endif
</div>
