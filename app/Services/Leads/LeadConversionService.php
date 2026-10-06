<?php

namespace App\Services\Leads;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadConversionService
{
    /**
     * Convierte un lead calificado en empresa + contacto (+ oportunidad opcional).
     *
     * Todo ocurre dentro de una transacción atómica: cualquier fallo revierte
     * la operación completa (sin registros parciales). El orden importa: la
     * empresa se crea ANTES de validar la coherencia del contacto existente,
     * de modo que un fallo ahí también revierte la empresa creada.
     *
     * @param  array<string, mixed>  $data  Datos validados de ConvertLeadRequest.
     * @return array{company: Company, contact: Contact, opportunity: ?Opportunity}
     *
     * @throws ValidationException
     */
    public function convert(Lead $lead, User $actor, array $data): array
    {
        return DB::transaction(function () use ($lead, $actor, $data) {
            $lead = Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail();

            if ($lead->trashed()) {
                throw ValidationException::withMessages([
                    'lead' => 'No se puede convertir un lead eliminado.',
                ]);
            }

            if ($lead->isConverted()) {
                throw ValidationException::withMessages([
                    'lead' => 'Este lead ya fue convertido.',
                ]);
            }

            if ($lead->status !== 'qualified') {
                throw ValidationException::withMessages([
                    'lead' => 'Solo se pueden convertir leads calificados (qualified). Califica el lead primero.',
                ]);
            }

            $ownerId = $data['owner_id'] ?? $lead->owner_id;
            DataScope::assertCanAssignUser($actor, $ownerId, $lead->owner_id);

            // ---- Empresa (se crea antes de validar el contacto: la transacción revierte si algo falla después).
            if (($data['company_mode'] ?? 'new') === 'existing') {
                DataScope::assertVisibleId($actor, Company::class, $data['company_id'] ?? null);
                $company = Company::findOrFail($data['company_id']);
            } else {
                $company = Company::create([
                    'trade_name' => $data['company_name'],
                    'status' => 'active',
                    'owner_id' => $ownerId,
                ]);
            }

            // ---- Contacto.
            if (($data['contact_mode'] ?? 'new') === 'existing') {
                DataScope::assertVisibleId($actor, Contact::class, $data['contact_id'] ?? null);
                $contact = Contact::findOrFail($data['contact_id']);

                if ($contact->company_id !== null && $contact->company_id !== $company->id) {
                    throw ValidationException::withMessages([
                        'contact_id' => 'El contacto pertenece a otra empresa. Selecciónalo de forma coherente o crea uno nuevo.',
                    ]);
                }

                if ($contact->company_id === null) {
                    $contact->update(['company_id' => $company->id]);
                }
            } else {
                $contact = Contact::create([
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company_id' => $company->id,
                    'owner_id' => $ownerId,
                    'status' => 'active',
                ]);
            }

            // ---- Oportunidad opcional (dato backend; su UI completa es Fase 5).
            $opportunity = null;
            if (! empty($data['create_opportunity'])) {
                $pipeline = Pipeline::where('is_default', true)->where('status', 'active')->first()
                    ?? Pipeline::where('status', 'active')->orderBy('id')->first()
                    ?? Pipeline::orderBy('id')->firstOrFail();
                $stage = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->where('status', 'active')->orderBy('position')->first()
                    ?? $pipeline->stages()->orderBy('position')->firstOrFail();

                $opportunity = Opportunity::create([
                    'name' => $data['opportunity_name'],
                    'amount' => $data['opportunity_amount'] ?? $lead->estimated_value,
                    'currency' => 'USD',
                    'probability' => $stage->probability,
                    'expected_close_date' => $data['expected_close_date'] ?? null,
                    'status' => 'open',
                    'owner_id' => $ownerId,
                    'pipeline_id' => $pipeline->id,
                    'pipeline_stage_id' => $stage->id,
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'lead_id' => $lead->id,
                ]);
            }

            // ---- Trazabilidad en el lead.
            $lead->update([
                'status' => 'converted',
                'converted_at' => now(),
                'converted_company_id' => $company->id,
                'converted_contact_id' => $contact->id,
            ]);

            $lead->activities()->create([
                'type' => 'status_change',
                'subject' => 'Lead convertido',
                'description' => "Convertido a {$company->trade_name} / {$contact->first_name} {$contact->last_name}."
                    .($opportunity ? " Oportunidad: {$opportunity->name}." : ''),
                'status' => 'completed',
                'completed_at' => now(),
                'user_id' => $actor->id,
            ]);

            app(AuditService::class)->log(
                $actor,
                $lead,
                'lead.converted',
                ['status' => 'qualified'],
                [
                    'status' => 'converted',
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'opportunity_id' => $opportunity?->id,
                ]
            );

            return ['company' => $company, 'contact' => $contact, 'opportunity' => $opportunity];
        });
    }
}
