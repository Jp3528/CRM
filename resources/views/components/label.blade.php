@props(['value' => null, 'for' => null])

<label @if($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'mb-1 block text-sm font-medium text-slate-700']) }}>
    {{ $value ?? $slot }}
</label>
