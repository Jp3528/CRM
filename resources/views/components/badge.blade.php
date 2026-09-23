@props(['color' => 'slate'])

@php
$colors = [
    'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
    'green' => 'bg-green-50 text-green-700 ring-green-200',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-200',
    'red' => 'bg-red-50 text-red-700 ring-red-200',
    'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['slate'])]) }}>
    {{ $slot }}
</span>
