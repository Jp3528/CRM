<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas centrales de transición de estados. Toda la lógica vive aquí
 * (no repartida en controllers/vistas):
 *
 *   new      → open, pending
 *   open     → pending, resolved
 *   pending  → open, resolved
 *   resolved → closed, open (reapertura)
 *   closed   → open (reapertura)
 *
 * Solo resolved puede cerrarse (decisión documentada: flujo predecible).
 * Reabrir limpia resolved_at/closed_at. Cada cambio real genera un evento
 * system dentro de la misma transacción (con lock anti-races).
 */
class TicketStatusService
{
    public const TRANSITIONS = [
        'new' => ['open', 'pending'],
        'open' => ['pending', 'resolved'],
        'pending' => ['open', 'resolved'],
        'resolved' => ['closed', 'open'],
        'closed' => ['open'],
    ];

    /**
     * @return array{ticket: Ticket, changed: bool}
     *
     * @throws ValidationException
     */
    public function transition(Ticket $ticket, string $to, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $to, $actor) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->trashed()) {
                throw ValidationException::withMessages([
                    'status' => 'No se puede cambiar el estado de un ticket eliminado.',
                ]);
            }

            if ($to === $ticket->status) {
                return ['ticket' => $ticket, 'changed' => false];
            }

            if (! in_array($to, self::TRANSITIONS[$ticket->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "No se puede pasar de {$ticket->status} a {$to}.",
                ]);
            }

            $from = $ticket->status;

            $ticket->update([
                'status' => $to,
                'resolved_at' => $to === 'resolved' ? now() : null,
                'closed_at' => $to === 'closed' ? now() : null,
            ]);

            $ticket->messages()->create([
                'user_id' => $actor->id,
                'type' => TicketMessage::SYSTEM_TYPE,
                'body' => "Estado cambiado de {$from} a {$to} por {$actor->name}.",
                'is_internal' => true,
            ]);

            return ['ticket' => $ticket->fresh(), 'changed' => true];
        });
    }
}
