<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\Automations\AutomationTriggerDispatcher;
use App\Support\AutomationCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Hook central de eventos hacia automatizaciones.
 *
 * Una sola clase registrada en los 9 sujetos (sin duplicar hooks en
 * controllers/servicios). Los valores previos se capturan en `updating`
 * (pre-save, con originales intactos); el dispatcher difiere la ejecución
 * a after-commit, así el evento empresarial confirma primero.
 *
 * Sin triggers de deleted/restored en Fase 11.
 */
class AutomationObserver
{
    /** @var array<string, array<string, mixed>> */
    protected static array $previous = [];

    public static function reset(): void
    {
        static::$previous = [];
    }

    public function created(Model $model): void
    {
        $trigger = match ($model::class) {
            Lead::class => 'lead.created',
            Contact::class => 'contact.created',
            default => null,
        };

        if ($trigger === null) {
            return;
        }

        AutomationTriggerDispatcher::dispatch(
            $trigger,
            $model,
            $this->contextFor($model, $trigger),
            auth()->id(),
            (string) Str::uuid()
        );
    }

    public function updating(Model $model): void
    {
        static::$previous[$this->key($model)] = [
            'status' => $model->getOriginal('status'),
            'pipeline_stage_id' => $model->getOriginal('pipeline_stage_id'),
        ];
    }

    public function updated(Model $model): void
    {
        $key = $this->key($model);
        $prev = static::$previous[$key] ?? [];
        unset(static::$previous[$key]);

        $events = $this->changeEvents($model, $prev);

        if ($events === []) {
            return;
        }

        $correlation = (string) Str::uuid();

        foreach ($events as [$trigger, $context]) {
            AutomationTriggerDispatcher::dispatch(
                $trigger,
                $model,
                $context,
                auth()->id(),
                $correlation
            );
        }
    }

    /**
     * @param  array<string, mixed>  $prev
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function changeEvents(Model $model, array $prev): array
    {
        return match ($model::class) {
            Lead::class => $this->statusChanged($model, $prev, 'lead.status_changed'),
            Ticket::class => $this->statusChanged($model, $prev, 'ticket.status_changed'),
            Quote::class => $this->statusChanged($model, $prev, 'quote.status_changed'),
            Sale::class => $this->statusChanged($model, $prev, 'sale.status_changed'),
            Invoice::class => $this->statusChanged($model, $prev, 'invoice.status_changed'),
            Campaign::class => $this->statusChanged($model, $prev, 'campaign.status_changed'),
            Task::class => $this->taskEvents($model, $prev),
            Opportunity::class => $this->opportunityEvents($model, $prev),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $prev
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function statusChanged(Model $model, array $prev, string $trigger): array
    {
        $before = $prev['status'] ?? null;

        if ($before === null || $before === $model->status) {
            return [];
        }

        $context = $this->contextFor($model, $trigger);
        $context['previous_status'] = $before;

        return [[$trigger, $context]];
    }

    /**
     * @param  array<string, mixed>  $prev
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function taskEvents(Task $task, array $prev): array
    {
        $before = $prev['status'] ?? null;

        if ($before === null || $before === $task->status) {
            return [];
        }

        // Solo transición real hacia completada.
        if ($task->status !== 'completed' || $before === 'completed') {
            return [];
        }

        $context = $this->contextFor($task, 'task.completed');
        $context['previous_status'] = $before;

        return [['task.completed', $context]];
    }

    /**
     * @param  array<string, mixed>  $prev
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function opportunityEvents(Opportunity $opp, array $prev): array
    {
        $events = [];
        $beforeStage = $prev['pipeline_stage_id'] ?? null;
        $beforeStatus = $prev['status'] ?? null;

        if ($beforeStage !== null && (int) $beforeStage !== (int) $opp->pipeline_stage_id) {
            $context = $this->contextFor($opp, 'opportunity.stage_changed');
            $context['previous_stage_id'] = (int) $beforeStage;
            $context['previous_status'] = $beforeStatus;
            $events[] = ['opportunity.stage_changed', $context];

            // Eventos derivados sin duplicar: won/lost según estado resultante.
            if ($opp->status === 'won' && $beforeStatus !== 'won') {
                $events[] = ['opportunity.won', $this->contextFor($opp, 'opportunity.won')];
            } elseif ($opp->status === 'lost' && $beforeStatus !== 'lost') {
                $events[] = ['opportunity.lost', $this->contextFor($opp, 'opportunity.lost')];
            }
        }

        return $events;
    }

    /** @return array<string, mixed> */
    private function contextFor(Model $model, string $trigger): array
    {
        $subject = AutomationCatalog::subjectFor($trigger);

        return match ($subject) {
            'lead' => [
                'status' => $model->status,
                'source' => $model->source,
                'score' => $model->score !== null ? (int) $model->score : null,
                'estimated_value' => $model->estimated_value !== null ? (string) $model->estimated_value : null,
                'owner_id' => $model->owner_id !== null ? (int) $model->owner_id : null,
            ],
            'contact' => [
                'status' => $model->status,
                'owner_id' => $model->owner_id !== null ? (int) $model->owner_id : null,
                'company_id' => $model->company_id !== null ? (int) $model->company_id : null,
            ],
            'opportunity' => [
                'status' => $model->status,
                'stage_id' => $model->pipeline_stage_id !== null ? (int) $model->pipeline_stage_id : null,
                'amount' => $model->amount !== null ? (string) $model->amount : null,
                'probability' => $model->probability !== null ? (int) $model->probability : null,
                'owner_id' => $model->owner_id !== null ? (int) $model->owner_id : null,
            ],
            'task' => [
                'status' => $model->status,
                'priority' => $model->priority,
                'assigned_to' => $model->assigned_to !== null ? (int) $model->assigned_to : null,
            ],
            'ticket' => [
                'status' => $model->status,
                'priority' => $model->priority,
                'category_id' => $model->category_id !== null ? (int) $model->category_id : null,
                'assigned_to' => $model->assigned_to !== null ? (int) $model->assigned_to : null,
            ],
            'quote', 'sale', 'invoice' => [
                'status' => $model->status,
                'total' => $model->total !== null ? (string) $model->total : null,
                'owner_id' => $model->owner_id !== null ? (int) $model->owner_id : null,
            ],
            'campaign' => [
                'status' => $model->status,
                'type' => $model->type,
                'owner_id' => $model->owner_id !== null ? (int) $model->owner_id : null,
            ],
            default => [],
        };
    }

    private function key(Model $model): string
    {
        return $model::class.':'.$model->getKey();
    }
}
