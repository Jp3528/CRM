@props(['status' => 'active'])

@php
$map = [
    'active' => ['green', 'Activo'],
    'inactive' => ['slate', 'Inactivo'],
    'pending' => ['yellow', 'Pendiente'],
    'completed' => ['blue', 'Completada'],
];
[$color, $label] = $map[$status] ?? ['slate', ucfirst($status)];
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
