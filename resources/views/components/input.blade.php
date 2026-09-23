@props(['label' => null, 'error' => null, 'id' => null])

@php $inputId = $id ?? $attributes->get('id'); @endphp

<div>
    @if ($label)
        <x-label :for="$inputId" :value="$label" />
    @endif
    <input
        @if($inputId) id="{{ $inputId }}" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500']) }}
    >
    @if ($error)
        <x-input-error :message="$error" />
    @endif
</div>
