<?php

namespace App\Notifications;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OpportunityOwnerChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Opportunity $opportunity,
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
            'title' => 'Oportunidad asignada',
            'message' => "Se te ha asignado la oportunidad: {$this->opportunity->name}",
            'entity_type' => 'opportunity',
            'entity_id' => $this->opportunity->id,
            'url' => route('opportunities.show', $this->opportunity->id),
            'action' => 'opportunity.owner_changed',
            'assigner_id' => $this->assigner?->id,
            'dedup_key' => $this->dedupKey,
        ];
    }
}
