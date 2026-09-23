@props(['status' => 'active', 'label' => null])

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
    'resolved' => ['green', 'Resuelto'],
    'closed' => ['slate', 'Cerrado'],
    'qualified' => ['green', 'Calificado'],
    'unqualified' => ['slate', 'No calificado'],
    'converted' => ['blue', 'Convertido'],
    'open' => ['green', 'Abierta'],
    'draft' => ['slate', 'Borrador'],
    'sent' => ['blue', 'Enviada'],
    'confirmed' => ['blue', 'Confirmada'],
    'paid' => ['green', 'Pagada'],
    'overdue' => ['yellow', 'Vencida'],
    'accepted' => ['green', 'Aceptada'],
    'rejected' => ['red', 'Rechazada'],
    'expired' => ['yellow', 'Vencida'],
];
[$color, $defaultLabel] = $map[$status] ?? ['slate', ucfirst($status)];
@endphp

<x-badge :color="$color">{{ $label ?? $defaultLabel }}</x-badge>
