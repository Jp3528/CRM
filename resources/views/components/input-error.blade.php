@props(['message' => null])

@if ($message)
    <p {{ $attributes->merge(['class' => 'mt-1 text-sm text-red-600']) }}>{{ $message }}</p>
@elseif ($slot->isNotEmpty())
    <p {{ $attributes->merge(['class' => 'mt-1 text-sm text-red-600']) }}>{{ $slot }}</p>
@endif
