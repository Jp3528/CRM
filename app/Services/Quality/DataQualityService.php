<?php

namespace App\Services\Quality;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;

class DataQualityService
{
    /**
     * Calcula los diagnósticos de calidad de datos sobre los registros visibles para el actor.
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(User $actor): array
    {
        // 1. Contactos sin empresa asociada
        $contactsWithoutCompanyQuery = Contact::query()->whereNull('company_id');
        DataScope::scopeOwned($contactsWithoutCompanyQuery, $actor);
        $contactsWithoutCompanyCount = $contactsWithoutCompanyQuery->count();

        // 2. Leads sin canal de contacto (sin email ni teléfono)
        $leadsWithoutContactChannelQuery = Lead::query()
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '');
            })
            ->where(function ($q) {
                $q->whereNull('phone')->orWhere('phone', '');
            });
        DataScope::scopeOwned($leadsWithoutContactChannelQuery, $actor);
        $leadsWithoutContactChannelCount = $leadsWithoutContactChannelQuery->count();

        // 3. Oportunidades abiertas sin fecha esperada de cierre
        $opportunitiesWithoutCloseDateQuery = Opportunity::query()
            ->where('status', 'open')
            ->whereNull('expected_close_date');
        DataScope::scopeOwned($opportunitiesWithoutCloseDateQuery, $actor);
        $opportunitiesWithoutCloseDateCount = $opportunitiesWithoutCloseDateQuery->count();

        // 4. Contactos sin responsable asignado
        $contactsWithoutOwnerQuery = Contact::query()->whereNull('owner_id');
        DataScope::scopeOwned($contactsWithoutOwnerQuery, $actor);
        $contactsWithoutOwnerCount = $contactsWithoutOwnerQuery->count();

        // 5. Leads sin responsable asignado
        $leadsWithoutOwnerQuery = Lead::query()->whereNull('owner_id');
        DataScope::scopeOwned($leadsWithoutOwnerQuery, $actor);
        $leadsWithoutOwnerCount = $leadsWithoutOwnerQuery->count();

        // 6. Candidatos a duplicados en contactos (por email repetido dentro del alcance)
        $duplicateEmailQuery = Contact::query()
            ->select('email', DB::raw('count(*) as count'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email')
            ->havingRaw('count(*) > 1');
        DataScope::scopeOwned($duplicateEmailQuery, $actor);
        $duplicateEmails = $duplicateEmailQuery->limit(10)->get();

        return [
            'metrics' => [
                'contacts_without_company' => [
                    'title' => 'Contactos sin empresa',
                    'count' => $contactsWithoutCompanyCount,
                    'description' => 'Contactos individuales que no han sido asignados a ninguna organización comercial.',
                    'route' => route('contacts.index', ['no_company' => 1]),
                    'status' => $contactsWithoutCompanyCount > 0 ? 'warning' : 'ok',
                ],
                'leads_without_contact_channel' => [
                    'title' => 'Leads sin canal de contacto',
                    'count' => $leadsWithoutContactChannelCount,
                    'description' => 'Prospectos que carecen tanto de correo electrónico como de teléfono para seguimiento.',
                    'route' => route('leads.index'),
                    'status' => $leadsWithoutContactChannelCount > 0 ? 'danger' : 'ok',
                ],
                'opportunities_without_close_date' => [
                    'title' => 'Oportunidades abiertas sin fecha de cierre',
                    'count' => $opportunitiesWithoutCloseDateCount,
                    'description' => 'Oportunidades activas en el pipeline que impiden un forecast ponderado confiable.',
                    'route' => route('opportunities.index', ['status' => 'open']),
                    'status' => $opportunitiesWithoutCloseDateCount > 0 ? 'warning' : 'ok',
                ],
                'contacts_without_owner' => [
                    'title' => 'Contactos sin responsable asignado',
                    'count' => $contactsWithoutOwnerCount,
                    'description' => 'Contactos sin propietario comercial que pueden quedar desatendidos.',
                    'route' => route('contacts.index'),
                    'status' => $contactsWithoutOwnerCount > 0 ? 'warning' : 'ok',
                ],
                'leads_without_owner' => [
                    'title' => 'Leads sin responsable asignado',
                    'count' => $leadsWithoutOwnerCount,
                    'description' => 'Prospectos comerciales pendientes de distribución a vendedores.',
                    'route' => route('leads.index'),
                    'status' => $leadsWithoutOwnerCount > 0 ? 'warning' : 'ok',
                ],
            ],
            'duplicate_emails' => $duplicateEmails,
        ];
    }
}
