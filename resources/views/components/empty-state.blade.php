@props(['title' => 'Sin registros', 'message' => null, 'actionUrl' => null, 'actionLabel' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-slate-300 bg-white px-6 py-12 text-center']) }}>
    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
    @if ($message)<p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">{{ $message }}</p>@endif
    @if ($actionUrl && $actionLabel)
        <div class="mt-4">
            <a href="{{ $actionUrl }}" class="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">{{ $actionLabel }}</a>
        </div>
    @endif
</div>
