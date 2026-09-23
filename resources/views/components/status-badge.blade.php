@props(['status' => 'active'])

@php
$map = [
    'active' => ['green', 'Activo'],
    'inactive' => ['slate', 'Inactivo'],
    'pending' => ['yellow', 'Pendiente'],
    'in_progress' => ['blue', 'En progreso'],
    'completed' => ['green', 'Completada'],
    'cancelled' => ['slate', 'Cancelada'],
    'new' => ['blue', 'Nuevo'],
    'contacted' => ['yellow', 'Contactado'],
    'qualified' => ['green', 'Calificado'],
    'unqualified' => ['slate', 'No calificado'],
    'converted' => ['blue', 'Convertido'],
    'open' => ['green', 'Abierta'],
    'draft' => ['slate', 'Borrador'],
    'sent' => ['blue', 'Enviada'],
    'accepted' => ['green', 'Aceptada'],
    'rejected' => ['red', 'Rechazada'],
    'expired' => ['yellow', 'Vencida'],
];
[$color, $label] = $map[$status] ?? ['slate', ucfirst($status)];
@endphp

<x-badge :color="$color">{{ $label }}</x-badge>
