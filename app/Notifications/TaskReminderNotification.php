<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $type, // 'overdue' | 'due_soon'
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
        $statusText = $this->type === 'overdue' ? 'vencida' : 'próxima a vencer';

        return [
            'title' => "Recordatorio de tarea {$statusText}",
            'message' => "La tarea '{$this->task->title}' está {$statusText}.",
            'entity_type' => 'task',
            'entity_id' => $this->task->id,
            'url' => route('tasks.show', $this->task->id),
            'action' => "task.reminder.{$this->type}",
            'dedup_key' => $this->dedupKey,
        ];
    }
}
