<?php

namespace App\Services\Automations;

use App\Models\Activity;
use App\Models\Automation;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomationCatalog;
use App\Support\AutomationVariables;
use App\Support\DataScope;
use App\Support\RelatedEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Ejecutor central de acciones. Switch contra tipos conocidos del catálogo;
 * sin clases dinámicas, sin reflection, sin métodos arbitrarios.
 */
final class AutomationActionExecutor
{
    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed> Resultado estructurado (sin objetos).
     *
     * @throws ValidationException ante fallos de ejecución.
     */
    public function execute(array $action, Automation $automation, Model $subject, string $subjectKey, ?User $triggeredBy): array
    {
        $owner = $automation->owner;

        if (! $owner) {
            throw ValidationException::withMessages(['automation' => 'Sin responsable.']);
        }

        return match ($action['type'] ?? null) {
            'create_task' => $this->createTask($action, $automation, $owner, $subject, $subjectKey, $triggeredBy),
            'create_activity' => $this->createActivity($action, $automation, $owner, $subject, $triggeredBy),
            'assign_owner' => $this->assignOwner($action, $automation, $owner, $subject, $subjectKey, $triggeredBy),
            'add_to_campaign' => $this->addToCampaign($action, $automation, $owner, $subject, $subjectKey),
            default => throw ValidationException::withMessages(['action' => 'Tipo de acción no permitido.']),
        };
    }

    /** Revalidación funcional previa por tipo de acción. */
    public function requiredPermission(string $type): ?string
    {
        return match ($type) {
            'create_task' => 'tasks.create',
            'create_activity' => 'activities.create',
            'assign_owner' => null, // Se valida con can('update', subject).
            'add_to_campaign' => 'campaigns.update',
            default => null,
        };
    }

    /** @param  array<string, mixed>  $action */
    private function createTask(array $action, Automation $automation, User $owner, Model $subject, string $subjectKey, ?User $triggeredBy): array
    {
        $assigneeId = $this->resolveUserId(
            $action['assigned_to_mode'],
            $action['fixed_user_id'] ?? null,
            $owner,
            $subject,
            $triggeredBy
        );

        $assignee = $assigneeId ? User::find($assigneeId) : null;

        if (! $assignee || $assignee->status !== 'active') {
            throw ValidationException::withMessages(['action' => 'Asignado inválido o inactivo.']);
        }

        if (! in_array((int) $assignee->id, DataScope::filterableUserIds($owner), true)) {
            throw ValidationException::withMessages(['action' => 'Asignado fuera del alcance.']);
        }

        $vars = AutomationVariables::dataFor($subject);
        $morphClass = RelatedEntity::classFor($subjectKey);

        $task = Task::create([
            'title' => AutomationVariables::render($action['title'], $vars),
            'description' => isset($action['description'])
                ? AutomationVariables::render($action['description'], $vars)
                : "Tarea automática: {$automation->name}.",
            'status' => 'pending',
            'priority' => $action['priority'],
            'due_at' => now()->addDays($action['due_in_days'])->toDateTimeString(),
            'assigned_to' => $assignee->id,
            'created_by' => $owner->id,
            'taskable_type' => $morphClass,
            'taskable_id' => $morphClass ? $subject->getKey() : null,
        ]);

        return ['action' => 'create_task', 'status' => 'success', 'task_id' => $task->id];
    }

    /** @param  array<string, mixed>  $action */
    private function createActivity(array $action, Automation $automation, User $owner, Model $subject, ?User $triggeredBy): array
    {
        $actor = $action['actor_mode'] === 'triggering_user' && $triggeredBy?->status === 'active'
            ? $triggeredBy
            : $owner;

        $vars = AutomationVariables::dataFor($subject);

        $payload = [
            'type' => $action['activity_type'],
            'subject' => AutomationVariables::render($action['subject'], $vars),
            'description' => isset($action['description'])
                ? AutomationVariables::render($action['description'], $vars)
                : "Actividad automática: {$automation->name}.",
            'status' => 'completed',
            'completed_at' => now(),
            'user_id' => $actor->id,
        ];

        if (method_exists($subject, 'activities')) {
            $activity = $subject->activities()->create($payload);
        } else {
            $activity = Activity::create($payload + [
                'subjectable_type' => $subject::class,
                'subjectable_id' => $subject->getKey(),
            ]);
        }

        return ['action' => 'create_activity', 'status' => 'success', 'activity_id' => $activity->id];
    }

    /** @param  array<string, mixed>  $action */
    private function assignOwner(array $action, Automation $automation, User $owner, Model $subject, string $subjectKey, ?User $triggeredBy): array
    {
        if (! in_array($subjectKey, AutomationCatalog::OWNER_ASSIGNABLE, true)) {
            throw ValidationException::withMessages(['action' => 'Este registro no admite responsable.']);
        }

        $targetId = $this->resolveUserId(
            $action['target_mode'],
            $action['fixed_user_id'] ?? null,
            $owner,
            $subject,
            $triggeredBy
        );

        $target = $targetId ? User::find($targetId) : null;

        if (! $target || $target->status !== 'active') {
            throw ValidationException::withMessages(['action' => 'Responsable inválido o inactivo.']);
        }

        if (! in_array((int) $target->id, DataScope::filterableUserIds($owner), true)) {
            throw ValidationException::withMessages(['action' => 'Responsable fuera del alcance.']);
        }

        // Sin saltar Policy: el owner debe poder actualizar el subject.
        if (! $owner->can('update', $subject)) {
            throw ValidationException::withMessages(['action' => 'Sin permiso para reasignar.']);
        }

        $subject->update(['owner_id' => $target->id]);

        return [
            'action' => 'assign_owner',
            'status' => 'success',
            'owner_id' => $target->id,
            'subject_type' => $subjectKey,
            'subject_id' => $subject->getKey(),
        ];
    }

    /** @param  array<string, mixed>  $action */
    private function addToCampaign(array $action, Automation $automation, User $owner, Model $subject, string $subjectKey): array
    {
        if (! in_array($subjectKey, AutomationCatalog::CAMPAIGN_ELIGIBLE, true)) {
            throw ValidationException::withMessages(['action' => 'Solo contactos o leads.']);
        }

        $campaign = Campaign::find($action['campaign_id']);

        if (! $campaign || ! $owner->can('update', $campaign)) {
            throw ValidationException::withMessages(['action' => 'Campaña no disponible.']);
        }

        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages(['action' => 'La campaña ya no admite miembros.']);
        }

        if (! DataScope::canViewModel($owner, $subject)) {
            throw ValidationException::withMessages(['action' => 'Registro fuera del alcance.']);
        }

        $existing = CampaignMember::where('campaign_id', $campaign->id)
            ->where('member_type', $subjectKey)
            ->where('member_id', $subject->getKey())
            ->first();

        if ($existing) {
            return [
                'action' => 'add_to_campaign',
                'status' => 'success',
                'campaign_member_id' => $existing->id,
                'duplicate' => true,
            ];
        }

        $member = CampaignMember::create([
            'campaign_id' => $campaign->id,
            'member_type' => $subjectKey,
            'member_id' => $subject->getKey(),
            'status' => 'pending',
            'source' => 'automation',
            'added_by' => $owner->id,
        ]);

        // Solo agrega el miembro: nunca envía ni simula comunicaciones.

        return [
            'action' => 'add_to_campaign',
            'status' => 'success',
            'campaign_member_id' => $member->id,
            'campaign_id' => $campaign->id,
        ];
    }

    private function resolveUserId(
        string $mode,
        mixed $fixedUserId,
        User $owner,
        Model $subject,
        ?User $triggeredBy
    ): ?int {
        return match ($mode) {
            'automation_owner' => (int) $owner->id,
            'triggering_user' => $triggeredBy ? (int) $triggeredBy->id : null,
            'fixed_user' => $fixedUserId ? (int) $fixedUserId : null,
            // subject_owner: owner_id del subject si existe; si no, el automation owner.
            'subject_owner' => isset($subject->owner_id) && $subject->owner_id
                ? (int) $subject->owner_id
                : (int) $owner->id,
            default => null,
        };
    }
}
