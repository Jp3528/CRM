<?php

namespace App\Services\Automations;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Support\AutomationCatalog;
use App\Support\ConditionEvaluator;
use App\Support\DataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ejecuta automatizaciones activas ante un evento ya confirmado.
 *
 * Reglas:
 * - El evento empresarial original ya hizo commit; un fallo aquí nunca lo
 *   revierte (cada automatización usa su propia transacción).
 * - Una automatización defectuosa no bloquea a las demás (try/catch por
 *   automatización).
 * - Checks previos (owner, scope, permisos, condiciones) producen skipped;
 *   errores ejecutando acciones producen failed con rollback propio.
 */
final class AutomationRunner
{
    public function __construct(private AutomationActionExecutor $executor) {}

    /**
     * @param  array<string, mixed>  $context  Valores del trigger (sin PII duplicada).
     * @param  array<int, string>  $chain  Trazas automation:subject para reentradas.
     */
    public function handle(
        string $trigger,
        Model $subject,
        array $context = [],
        ?int $triggeredById = null,
        ?string $correlationId = null,
        int $depth = 0,
        ?string $eventUuid = null,
        array $chain = []
    ): void {
        if (! array_key_exists($trigger, AutomationCatalog::TRIGGERS)) {
            return;
        }

        if ($depth > AutomationCatalog::MAX_DEPTH) {
            return;
        }

        $subjectKey = AutomationCatalog::subjectForClass($subject::class);

        if ($subjectKey === null) {
            return;
        }

        $correlationId ??= (string) Str::uuid();
        $eventUuid ??= (string) Str::uuid();
        $triggeredBy = $triggeredById ? \App\Models\User::find($triggeredById) : null;

        $baseContext = array_merge($context, [
            'entity_type' => $subjectKey,
            'entity_id' => $subject->getKey(),
            'owner_id' => $subject->owner_id ?? $subject->assigned_to ?? null,
            'triggered_by' => $triggeredById,
            'correlation_id' => $correlationId,
            'depth' => $depth,
        ]);

        Automation::where('status', 'active')
            ->where('trigger_type', $trigger)
            // Owner completo (team_id + roles): el scope de equipo lo necesita.
            ->with(['owner' => fn ($q) => $q->with('roles')])
            ->chunkById(100, function ($automations) use (
                $trigger, $subject, $subjectKey, $baseContext,
                $triggeredBy, $correlationId, $depth, $eventUuid, $chain
            ) {
                foreach ($automations as $automation) {
                    try {
                        $this->executeOne(
                            $automation, $trigger, $subject, $subjectKey,
                            $baseContext, $triggeredBy, $correlationId,
                            $depth, $eventUuid, $chain
                        );
                    } catch (Throwable $e) {
                        // Aislamiento: una automatización defectuosa no bloquea otras.
                        Log::error('Automation execution error', [
                            'automation_id' => $automation->id,
                            'trigger' => $trigger,
                        ]);
                    }
                }
            });
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $chain
     */
    private function executeOne(
        Automation $automation,
        string $trigger,
        Model $subject,
        string $subjectKey,
        array $context,
        ?\App\Models\User $triggeredBy,
        string $correlationId,
        int $depth,
        string $eventUuid,
        array $chain
    ): void {
        // Guard de reentrada: misma automatización + subject + trigger en la cadena.
        $trace = "{$automation->id}:{$subjectKey}:{$subject->getKey()}:{$trigger}";

        if (in_array($trace, $chain, true)) {
            return;
        }

        // Dedup: mismo evento no ejecuta dos veces por automatización.
        try {
            $run = AutomationRun::create([
                'automation_id' => $automation->id,
                'event_uuid' => $eventUuid,
                'trigger_type' => $trigger,
                'subject_type' => $subjectKey,
                'subject_id' => $subject->getKey(),
                'status' => 'running',
                'triggered_by' => $triggeredBy?->id,
                'context' => $context,
                'started_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Violación del unique (automation_id, event_uuid): duplicado seguro.
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'automation_runs_automation_id_event_uuid_unique')) {
                return;
            }

            throw $e;
        }

        // Checks dinámicos (nunca autoridad histórica).
        $skipReason = $this->precheck($automation, $subject);

        if ($skipReason !== null) {
            $this->finishSkipped($run, $automation, $skipReason);

            return;
        }

        $conditions = $automation->conditions ?? [];
        $fieldTypes = AutomationCatalog::fieldsForTrigger($trigger);

        if ($conditions !== [] && ! ConditionEvaluator::passes($conditions, $fieldTypes, $context)) {
            $this->finishSkipped($run, $automation, 'conditions_not_met', ['conditions' => count($conditions)]);

            return;
        }

        // Recheck funcional por acción antes de ejecutar.
        foreach ($automation->actions ?? [] as $action) {
            $required = $this->executor->requiredPermission($action['type'] ?? '');

            if ($required && ! $automation->owner->hasPermission($required)) {
                $this->finishSkipped($run, $automation, 'permission_denied', ['permission' => $required]);

                return;
            }
        }

        try {
            $results = DB::transaction(function () use ($automation, $subject, $subjectKey, $triggeredBy) {
                $out = [];

                foreach ($automation->actions ?? [] as $action) {
                    $out[] = $this->executor->execute($action, $automation, $subject->fresh() ?? $subject, $subjectKey, $triggeredBy);
                }

                return $out;
            });

            $run->update([
                'status' => 'success',
                'result' => ['actions' => $results],
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Rollback propio de las acciones; el evento empresarial NO se revierte.
            $run->update([
                'status' => 'failed',
                'error_message' => mb_substr($this->safeMessage($e), 0, 500),
                'finished_at' => now(),
            ]);

            Log::error('Automation run failed', [
                'automation_id' => $automation->id,
                'run_id' => $run->id,
            ]);
        }

        $automation->update(['last_run_at' => now()]);
    }

    /**
     * @return string|null reason de skip o null si puede continuar.
     */
    private function precheck(Automation $automation, Model $subject): ?string
    {
        $owner = $automation->owner;

        if (! $owner) {
            return 'owner_missing';
        }

        if ($owner->status !== 'active') {
            return 'owner_inactive';
        }

        $fresh = $subject->fresh();

        if (! $fresh || (method_exists($fresh, 'trashed') && $fresh->trashed())) {
            return 'subject_unavailable';
        }

        if (! DataScope::canViewModel($owner, $fresh)) {
            return 'scope_denied';
        }

        return null;
    }

    /** @param  array<string, mixed>  $extra */
    private function finishSkipped(AutomationRun $run, Automation $automation, string $reason, array $extra = []): void
    {
        $run->update([
            'status' => 'skipped',
            'result' => array_merge(['reason' => $reason], $extra),
            'finished_at' => now(),
        ]);

        $automation->update(['last_run_at' => now()]);
    }

    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            $first = collect($e->errors())->flatten()->first();

            return is_string($first) && $first !== '' ? $first : 'Error de ejecución.';
        }

        // Mensaje genérico: sin paths, SQL, secrets ni stack trace en UI.
        return 'Error interno al ejecutar la automatización.';
    }
}
