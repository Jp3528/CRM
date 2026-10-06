<?php

namespace App\Services\Notifications;

use App\Models\DataImport;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\OpportunityOwnerChangedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskReminderNotification;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class InternalNotificationService
{
    /**
     * Envía una notificación interna respetando deduplicación y estado activo,
     * siempre difiriendo el despacho real hasta después del commit de la base de datos.
     */
    public function send(User $recipient, Notification $notification, ?string $dedupKey = null): bool
    {
        if ($recipient->status !== 'active') {
            return false;
        }

        if ($dedupKey !== null) {
            $alreadySent = $recipient->notifications()
                ->where('data', 'like', "%\"dedup_key\":\"{$dedupKey}\"%")
                ->exists();

            if ($alreadySent) {
                return false;
            }
        }

        DB::afterCommit(function () use ($recipient, $notification) {
            $recipient->notify($notification);
        });

        return true;
    }

    public function sendTaskAssigned(Task $task, ?User $assigner = null): bool
    {
        $recipient = $task->assignee;
        if (! $recipient || ($assigner && $assigner->id === $recipient->id)) {
            return false;
        }

        $dedupKey = "task_assigned_{$task->id}_{$recipient->id}";
        $notification = new TaskAssignedNotification($task, $assigner, $dedupKey);

        return $this->send($recipient, $notification, $dedupKey);
    }

    public function sendOpportunityOwnerChanged(Opportunity $opportunity, ?User $assigner = null): bool
    {
        $recipient = $opportunity->owner;
        if (! $recipient || ($assigner && $assigner->id === $recipient->id)) {
            return false;
        }

        $dedupKey = "opportunity_owner_{$opportunity->id}_{$recipient->id}";
        $notification = new OpportunityOwnerChangedNotification($opportunity, $assigner, $dedupKey);

        return $this->send($recipient, $notification, $dedupKey);
    }

    public function sendTicketAssigned(Ticket $ticket, ?User $assigner = null): bool
    {
        $recipient = $ticket->assignedUser;
        if (! $recipient || ($assigner && $assigner->id === $recipient->id)) {
            return false;
        }

        $dedupKey = "ticket_assigned_{$ticket->id}_{$recipient->id}";
        $notification = new TicketAssignedNotification($ticket, $assigner, $dedupKey);

        return $this->send($recipient, $notification, $dedupKey);
    }

    public function sendImportCompleted(DataImport $import): bool
    {
        $recipient = $import->creator;
        if (! $recipient) {
            return false;
        }

        $dedupKey = "import_completed_{$import->id}_{$recipient->id}";
        $notification = new ImportCompletedNotification($import, $dedupKey);

        return $this->send($recipient, $notification, $dedupKey);
    }

    public function sendTaskReminder(Task $task, string $type, string $dateKey): bool
    {
        $recipient = $task->assignee;
        if (! $recipient) {
            return false;
        }

        $dedupKey = "task_reminder_{$task->id}_{$type}_{$dateKey}";
        $notification = new TaskReminderNotification($task, $type, $dedupKey);

        return $this->send($recipient, $notification, $dedupKey);
    }
}
