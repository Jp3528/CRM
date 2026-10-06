<?php

namespace App\Services\Opportunities;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityStageService
{
    /**
     * Crea una oportunidad sincronizando estado/probabilidad/cierre con la etapa.
     * Registra historial inicial (from null) + actividad.
     *
     * @param  array<string, mixed>  $data  Datos validados de StoreOpportunityRequest.
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): Opportunity
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null);
            DataScope::assertVisibleId($actor, Company::class, $data['company_id'] ?? null);
            DataScope::assertVisibleId($actor, Contact::class, $data['contact_id'] ?? null);
            DataScope::assertVisibleId($actor, Lead::class, $data['lead_id'] ?? null);
            DataScope::assertCanAssignUser($actor, $data['owner_id'] ?? null);

            $stage = PipelineStage::findOrFail($data['pipeline_stage_id']);

            if ($stage->pipeline_id !== (int) $data['pipeline_id']) {
                throw ValidationException::withMessages([
                    'pipeline_stage_id' => 'La etapa no pertenece al pipeline seleccionado.',
                ]);
            }

            if ($stage->is_lost && blank($data['loss_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'loss_reason' => 'Indica el motivo de pérdida para crear en etapa Perdida.',
                ]);
            }

            // Coherencia empresa/contacto (autoridad backend, no solo frontend).
            if (! empty($data['contact_id'])) {
                $contact = Contact::findOrFail($data['contact_id']);
                if ($contact->company_id !== null && (int) $contact->company_id !== (int) $data['company_id']) {
                    throw ValidationException::withMessages([
                        'contact_id' => 'El contacto pertenece a otra empresa.',
                    ]);
                }
            }

            $sync = $this->syncAttributes($stage, $data['loss_reason'] ?? null, null);

            $opportunity = Opportunity::create(array_merge($data, $sync));

            // Vincula el contacto si aún no tiene empresa (explícito, misma empresa).
            if (! empty($contact) && $contact->company_id === null) {
                $contact->update(['company_id' => $opportunity->company_id]);
            }

            $opportunity->stageHistory()->create([
                'from_stage_id' => null,
                'to_stage_id' => $stage->id,
                'changed_by' => $actor->id,
                'changed_at' => now(),
                'notes' => 'Creación de la oportunidad.',
            ]);

            $opportunity->activities()->create([
                'type' => 'status_change',
                'subject' => 'Oportunidad creada',
                'description' => "Creada en etapa {$stage->name}.",
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $actor->id,
            ]);

            return $opportunity->fresh();
        });
    }

    /**
     * Mueve una oportunidad de etapa de forma atómica (lock + transacción).
     * Sin cambios si el destino es la etapa actual (no genera historial).
     *
     * @return array{opportunity: Opportunity, moved: bool}
     *
     * @throws ValidationException
     */
    public function move(Opportunity $opportunity, User $actor, int $stageId, ?string $lossReason = null, ?string $notes = null): array
    {
        return DB::transaction(function () use ($opportunity, $actor, $stageId, $lossReason, $notes) {
            $opportunity = Opportunity::whereKey($opportunity->id)->lockForUpdate()->firstOrFail();

            if ($opportunity->trashed()) {
                throw ValidationException::withMessages([
                    'opportunity' => 'No se puede mover una oportunidad eliminada.',
                ]);
            }

            $stage = PipelineStage::findOrFail($stageId);

            if ($stage->pipeline_id !== $opportunity->pipeline_id) {
                throw ValidationException::withMessages([
                    'pipeline_stage_id' => 'La etapa no pertenece al pipeline de la oportunidad.',
                ]);
            }

            if ($stage->id === $opportunity->pipeline_stage_id) {
                return ['opportunity' => $opportunity, 'moved' => false];
            }

            if ($stage->is_lost && blank($lossReason)) {
                throw ValidationException::withMessages([
                    'loss_reason' => 'Indica el motivo de pérdida para mover a Perdida.',
                ]);
            }

            $fromStageId = $opportunity->pipeline_stage_id;
            $fromName = $opportunity->stage?->name ?? '—';

            $opportunity->update(array_merge(
                ['pipeline_stage_id' => $stage->id],
                $this->syncAttributes($stage, $lossReason, $opportunity->actual_close_date)
            ));

            $opportunity->stageHistory()->create([
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $stage->id,
                'changed_by' => $actor->id,
                'changed_at' => now(),
                'notes' => $notes,
            ]);

            $opportunity->activities()->create([
                'type' => 'status_change',
                'subject' => "Etapa cambiada a {$stage->name}",
                'description' => "De {$fromName} a {$stage->name}."
                    .($stage->is_lost && $lossReason ? " Motivo: {$lossReason}." : ''),
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $actor->id,
            ]);

            return ['opportunity' => $opportunity->fresh(), 'moved' => true];
        });
    }

    /**
     * Sincroniza status/probability/actual_close_date/loss_reason con la etapa.
     * Fuente única de verdad: PipelineStage (is_won/is_lost/probability).
     *
     * @return array<string, mixed>
     */
    private function syncAttributes(PipelineStage $stage, ?string $lossReason, mixed $currentCloseDate): array
    {
        if ($stage->is_won) {
            return [
                'status' => 'won',
                'probability' => 100,
                'actual_close_date' => $currentCloseDate ?: now()->toDateString(),
                'loss_reason' => null,
            ];
        }

        if ($stage->is_lost) {
            return [
                'status' => 'lost',
                'probability' => 0,
                'actual_close_date' => now()->toDateString(),
                'loss_reason' => $lossReason,
            ];
        }

        return [
            'status' => 'open',
            'probability' => $stage->probability,
            'actual_close_date' => null,
            'loss_reason' => null,
        ];
    }

    /**
     * Sincroniza la probabilidad de las oportunidades abiertas vinculadas a una etapa cuando esta cambia.
     * Mantiene los cierres históricos (won/lost) sin modificar.
     */
    public function syncOpenOpportunitiesProbability(PipelineStage $stage): int
    {
        if ($stage->is_won || $stage->is_lost) {
            return 0;
        }

        return Opportunity::where('pipeline_stage_id', $stage->id)
            ->where('status', 'open')
            ->update(['probability' => $stage->probability]);
    }
}
