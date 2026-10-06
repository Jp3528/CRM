<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ?User $assigner = null,
        public ?string $dedupKey = null
    ) {}

    public function via(object $notifiable): array
    {
        if (isset($notifiable->status) && $notifiable->status !== 'active') {
            return [];
        }

        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Ticket asignado',
            'message' => "Se te ha asignado el ticket #{$this->ticket->id}: {$this->ticket->subject}",
            'entity_type' => 'ticket',
            'entity_id' => $this->ticket->id,
            'url' => route('tickets.show', $this->ticket->id),
            'action' => 'ticket.assigned',
            'assigner_id' => $this->assigner?->id,
            'dedup_key' => $this->dedupKey,
        ];
    }
}
