<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignMemberRequest;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Support\CampaignMemberType;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CampaignMemberController extends Controller
{
    /**
     * Constructor de audiencia básico (filtros + preview scoped).
     */
    public function audience(Campaign $campaign, Request $request): View
    {
        $this->authorize('view', $campaign);

        $user = $request->user();

        $validated = $request->validate([
            'tab' => ['nullable', 'string', 'in:contacts,leads'],
            'contact_status' => ['nullable', 'string', 'max:30'],
            'contact_owner' => ['nullable', 'integer'],
            'contact_company' => ['nullable', 'integer'],
            'contact_tag' => ['nullable', 'integer'],
            'lead_status' => ['nullable', 'string', 'max:30'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'lead_score_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lead_owner' => ['nullable', 'integer'],
            'lead_tag' => ['nullable', 'integer'],
        ]);

        $tab = $validated['tab'] ?? 'contacts';

        $contactPreview = $this->contactAudienceQuery($user, $validated)->count();
        $leadPreview = $this->leadAudienceQuery($user, $validated)->count();

        // IDs ya miembros (para no duplicar en la vista previa informativa).
        $existingContacts = $campaign->members()->where('member_type', 'contact')->pluck('member_id')->all();
        $existingLeads = $campaign->members()->where('member_type', 'lead')->pluck('member_id')->all();

        return view('campaigns.audience', [
            'campaign' => $campaign,
            'tab' => $tab,
            'filters' => $validated,
            'contactPreview' => $contactPreview,
            'leadPreview' => $leadPreview,
            'existingContacts' => $existingContacts,
            'existingLeads' => $existingLeads,
            'contactStatuses' => Contact::STATUSES,
            'leadStatuses' => Lead::STATUSES,
            'leadSources' => Lead::SOURCES,
            'owners' => DataScope::filterableUsers($user),
            'companies' => Company::visibleTo($user)->orderBy('trade_name')->get(['id', 'trade_name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'canManage' => $user->can('update', $campaign) && $campaign->isEditable(),
        ]);
    }

    public function store(StoreCampaignMemberRequest $request, Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isEditable(), 422, 'La campaña ya no admite miembros.');

        $data = $request->validated();
        $user = $request->user();

        $class = CampaignMemberType::classFor($data['member_type']);
        abort_unless($class !== null, 422, 'Tipo de miembro no permitido.');
        DataScope::assertVisibleId($user, $class, $data['member_id']);

        $exists = CampaignMember::where('campaign_id', $campaign->id)
            ->where('member_type', $data['member_type'])
            ->where('member_id', $data['member_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Ese registro ya es miembro de la campaña.');
        }

        CampaignMember::create([
            'campaign_id' => $campaign->id,
            'member_type' => $data['member_type'],
            'member_id' => $data['member_id'],
            'status' => 'pending',
            'source' => $data['source'] ?? 'manual',
            'added_by' => $user->id,
        ]);

        return back()->with('success', 'Miembro agregado correctamente.');
    }

    /**
     * Alta masiva desde el constructor de audiencia. Revalida alcance en backend,
     * ignora duplicados y respeta el límite de 500 por operación.
     */
    public function bulkStore(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        abort_unless($campaign->isEditable(), 422, 'La campaña ya no admite miembros.');

        $validated = $request->validate([
            'tab' => ['required', 'string', 'in:contacts,leads'],
            'contact_status' => ['nullable', 'string', 'max:30'],
            'contact_owner' => ['nullable', 'integer'],
            'contact_company' => ['nullable', 'integer'],
            'contact_tag' => ['nullable', 'integer'],
            'lead_status' => ['nullable', 'string', 'max:30'],
            'lead_source' => ['nullable', 'string', 'max:100'],
            'lead_score_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lead_owner' => ['nullable', 'integer'],
            'lead_tag' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $tab = $validated['tab'];

        $query = $tab === 'contacts'
            ? $this->contactAudienceQuery($user, $validated)
            : $this->leadAudienceQuery($user, $validated);

        $ids = $query->pluck($tab === 'contacts' ? 'contacts.id' : 'leads.id')->all();

        if (count($ids) > 500) {
            return back()->with('error', 'Selección demasiado grande: máximo 500 miembros por operación.');
        }

        $existing = CampaignMember::where('campaign_id', $campaign->id)
            ->where('member_type', $tab === 'contacts' ? 'contact' : 'lead')
            ->whereIn('member_id', $ids === [] ? [0] : $ids)
            ->pluck('member_id')
            ->all();

        $fresh = array_values(array_diff($ids, $existing));

        // Revalidación de alcance por ID (sin confiar en el frontend).
        $visible = DataScope::visibleIds($user, $tab === 'contacts' ? Contact::class : Lead::class);
        if ($visible !== null) {
            $fresh = array_values(array_intersect($fresh, $visible));
        }

        DB::transaction(function () use ($campaign, $tab, $fresh, $user) {
            foreach (array_chunk($fresh, 100) as $chunk) {
                foreach ($chunk as $id) {
                    CampaignMember::create([
                        'campaign_id' => $campaign->id,
                        'member_type' => $tab === 'contacts' ? 'contact' : 'lead',
                        'member_id' => $id,
                        'status' => 'pending',
                        'source' => 'audience',
                        'added_by' => $user->id,
                    ]);
                }
            }
        });

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', count($fresh).' miembro(s) agregados desde la audiencia.');
    }

    public function destroy(Campaign $campaign, CampaignMember $member): RedirectResponse
    {
        $this->authorize('update', $campaign);

        abort_unless($member->campaign_id === $campaign->id, 404);
        abort_unless($campaign->isEditable(), 422, 'La campaña ya no admite cambios de miembros.');

        if ($member->communications()->exists()) {
            return back()->with('error', 'No se puede quitar: el miembro ya tiene comunicaciones registradas.');
        }

        $member->delete();

        return back()->with('success', 'Miembro retirado correctamente.');
    }

    public function unsubscribe(Campaign $campaign, CampaignMember $member): RedirectResponse
    {
        $this->authorize('update', $campaign);

        abort_unless($member->campaign_id === $campaign->id, 404);

        $member->update(['status' => 'unsubscribed']);

        return back()->with('success', 'Miembro marcado como dado de baja.');
    }

    /** @param  array<string, mixed>  $filters */
    private function contactAudienceQuery(User $user, array $filters)
    {
        $query = Contact::visibleTo($user)->select('contacts.id');

        if (! empty($filters['contact_status'])) {
            $query->where('contacts.status', $filters['contact_status']);
        }

        if (! empty($filters['contact_owner'])) {
            $query->where('contacts.owner_id', $filters['contact_owner']);
        }

        if (! empty($filters['contact_company'])) {
            // La empresa del filtro también debe estar en alcance.
            if (DataScope::isVisibleId($user, Company::class, $filters['contact_company'])) {
                $query->where('contacts.company_id', $filters['contact_company']);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['contact_tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['contact_tag']));
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filters */
    private function leadAudienceQuery(User $user, array $filters)
    {
        $query = Lead::visibleTo($user)->select('leads.id');

        if (! empty($filters['lead_status'])) {
            $query->where('leads.status', $filters['lead_status']);
        }

        if (! empty($filters['lead_source'])) {
            $query->where('leads.source', $filters['lead_source']);
        }

        if (isset($filters['lead_score_min']) && $filters['lead_score_min'] !== '' && $filters['lead_score_min'] !== null) {
            $query->where('leads.score', '>=', (int) $filters['lead_score_min']);
        }

        if (! empty($filters['lead_owner'])) {
            $query->where('leads.owner_id', $filters['lead_owner']);
        }

        if (! empty($filters['lead_tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['lead_tag']));
        }

        return $query;
    }
}
