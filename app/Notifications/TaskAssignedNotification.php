<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
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
            'title' => 'Nueva tarea asignada',
            'message' => "Se te ha asignado la tarea: {$this->task->title}",
            'entity_type' => 'task',
            'entity_id' => $this->task->id,
            'url' => route('tasks.show', $this->task->id),
            'action' => 'task.assigned',
            'assigner_id' => $this->assigner?->id,
            'dedup_key' => $this->dedupKey,
        ];
    }
}
