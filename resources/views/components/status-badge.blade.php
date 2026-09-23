@props(['status' => 'active'])

@php
$map = [
    'active' => ['green', 'Activo'],
    'inactive' => ['slate', 'Inactivo'],
    'pending' => ['yellow', 'Pendiente'],
    'completed' => ['blue', 'Completada'],
    'new' => ['blue', 'Nuevo'],
    'contacted' => ['yellow', 'Contactado'],
    'qualified' => ['green', 'Calificado'],
    'unqualified' => ['slate', 'No calificado'],
    'converted' => ['blue', 'Convertido'],
    'open' => ['green', 'Abierta'],
];
[$color, $label] = $map[$status] ?? ['slate', ucfirst($status)];
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
