<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommunicationRequest;
use App\Http\Requests\UpdateCommunicationRequest;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Campaigns\CampaignCommunicationService;
use App\Support\DataScope;
use App\Support\TemplateVariables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Communication::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'channel' => ['nullable', 'string', 'max:30'],
            'campaign_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Communication::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $communications = Communication::query()
            ->visibleTo($user)
            ->with([
                'campaign' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'owner_id'),
                'lead' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'owner_id'),
                'template:id,name',
                'owner:id,name',
            ])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->channel($validated['channel'] ?? null)
            ->forCampaign($validated['campaign_id'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('communications.index', [
            'communications' => $communications,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'channel' => $validated['channel'] ?? '',
                'campaign_id' => $validated['campaign_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Communication::STATUSES,
            'channels' => Communication::CHANNELS,
            'campaigns' => Campaign::visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Communication::class);

        $user = $request->user();
        $preselectedCampaignId = $request->integer('campaign_id') ?: null;
        $preselectedContactId = $request->integer('contact_id') ?: null;
        $preselectedLeadId = $request->integer('lead_id') ?: null;
        $preselectedTemplateId = $request->integer('template_id') ?: null;

        if (! DataScope::isVisibleId($user, Campaign::class, $preselectedCampaignId)) {
            $preselectedCampaignId = null;
        }

        if (! DataScope::isVisibleId($user, Contact::class, $preselectedContactId)) {
            $preselectedContactId = null;
        }

        if (! DataScope::isVisibleId($user, Lead::class, $preselectedLeadId)) {
            $preselectedLeadId = null;
        }

        if (! DataScope::isVisibleId($user, MessageTemplate::class, $preselectedTemplateId)) {
            $preselectedTemplateId = null;
        }

        return view('communications.create', array_merge(
            $this->formData($user),
            [
                'preselectedCampaignId' => $preselectedCampaignId,
                'preselectedContactId' => $preselectedContactId,
                'preselectedLeadId' => $preselectedLeadId,
                'preselectedTemplateId' => $preselectedTemplateId,
                'variables' => TemplateVariables::VARIABLES,
            ]
        ));
    }

    public function store(StoreCommunicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DataScope::assertVisibleId($user, Campaign::class, $data['campaign_id'] ?? null);
        DataScope::assertVisibleId($user, Contact::class, $data['contact_id'] ?? null);
        DataScope::assertVisibleId($user, Lead::class, $data['lead_id'] ?? null);
        DataScope::assertVisibleId($user, MessageTemplate::class, $data['template_id'] ?? null);
        $data['owner_id'] = DataScope::normalizeOwnerId($user, $data['owner_id'] ?? null);
        DataScope::assertCanAssignUser($user, $data['owner_id'] ?? null);

        // Si se indica miembro, debe pertenecer a la campaña y ser visible.
        $member = null;
        if (! empty($data['campaign_member_id'])) {
            $member = CampaignMember::findOrFail($data['campaign_member_id']);

            if (! empty($data['campaign_id']) && (int) $member->campaign_id !== (int) $data['campaign_id']) {
                abort(422, 'El miembro no pertenece a la campaña indicada.');
            }

            $campaignOfMember = Campaign::find($member->campaign_id);
            if ($campaignOfMember) {
                $this->authorize('view', $campaignOfMember);
            }

            abort_unless(DataScope::canViewCampaignMember($user, $member), 403);
        }

        // Interpolación segura de variables si hay plantilla y objetivo.
        if (! empty($data['template_id']) && str_contains($data['body'], '{{')) {
            $target = ! empty($data['contact_id'])
                ? Contact::find($data['contact_id'])
                : Lead::find($data['lead_id']);
            $data['body'] = TemplateVariables::render($data['body'], TemplateVariables::dataFor($target));
        }

        $communication = DB::transaction(function () use ($data, $user, $member) {
            return Communication::create([
                'campaign_id' => $data['campaign_id'] ?? $member?->campaign_id,
                'campaign_member_id' => $data['campaign_member_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'channel' => $data['channel'],
                'direction' => 'outbound',
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'],
                'status' => 'draft',
                'owner_id' => $data['owner_id'] ?? $user->id,
                'created_by' => $user->id,
                'metadata' => ['simulated' => false, 'provider' => null],
            ]);
        });

        return redirect()->route('communications.show', $communication)
            ->with('success', 'Comunicación registrada como borrador interno (sin envío externo).');
    }

    public function show(Communication $communication): View
    {
        $this->authorize('view', $communication);

        $user = request()->user();

        $communication->load([
            'campaign' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
            'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'email', 'owner_id'),
            'lead' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'email', 'owner_id'),
            'template:id,name,channel',
            'owner:id,name',
            'creator:id,name',
        ]);

        return view('communications.show', [
            'communication' => $communication,
            'canUpdate' => $user->can('update', $communication),
            'canSimulate' => $user->can('update', $communication)
                && in_array($communication->status, ['draft', 'queued'], true),
            'canViewCampaign' => DataScope::canViewModel($user, $communication->campaign),
            'canViewContact' => $communication->contact_id
                ? DataScope::isVisibleId($user, Contact::class, $communication->contact_id)
                : false,
            'canViewLead' => $communication->lead_id
                ? DataScope::isVisibleId($user, Lead::class, $communication->lead_id)
                : false,
        ]);
    }

    public function edit(Communication $communication): View
    {
        $this->authorize('update', $communication);

        abort_unless(in_array($communication->status, Communication::EDITABLE_STATUSES, true), 422, 'Solo borradores o en cola se pueden editar.');

        return view('communications.edit', [
            'communication' => $communication,
        ]);
    }

    public function update(UpdateCommunicationRequest $request, Communication $communication): RedirectResponse
    {
        $communication->update($request->validated());

        return redirect()->route('communications.show', $communication)
            ->with('success', 'Comunicación actualizada correctamente.');
    }

    public function destroy(Communication $communication): RedirectResponse
    {
        $this->authorize('delete', $communication);

        $communication->delete();

        return redirect()->route('communications.index')
            ->with('success', 'Comunicación eliminada correctamente.');
    }

    /**
     * Registra el envío simulado (sin proveedor externo).
     */
    public function simulate(Communication $communication): RedirectResponse
    {
        $this->authorize('update', $communication);

        CampaignCommunicationService::simulateSingle($communication, request()->user());

        return back()->with('success', 'Envío simulado registrado (sin envío externo).');
    }

    /**
     * Comunicación masiva simulada para miembros elegibles de una campaña.
     * Límite de 500 miembros por operación.
     */
    public function bulkStore(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('view', $campaign);
        $this->authorize('create', Communication::class);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:email,sms,whatsapp'],
            'template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'member_ids' => ['sometimes', 'array', 'max:500'],
            'member_ids.*' => ['integer', 'min:1'],
        ]);

        $user = $request->user();
        DataScope::assertVisibleId($user, MessageTemplate::class, $validated['template_id'] ?? null);

        $membersQuery = $campaign->members()->where('status', '!=', 'unsubscribed');
        DataScope::scopeCampaignMembers($membersQuery, $user);

        if (! empty($validated['member_ids'])) {
            // Revalidación: solo IDs de esta campaña y visibles.
            $membersQuery->whereIn('campaign_members.id', $validated['member_ids'])
                ->where('campaign_members.campaign_id', $campaign->id);
        } else {
            $membersQuery->where('campaign_members.campaign_id', $campaign->id);
        }

        $members = $membersQuery->get();

        if ($members->count() > CampaignCommunicationService::BULK_LIMIT) {
            return back()->with('error', 'Límite superado: máximo '.CampaignCommunicationService::BULK_LIMIT.' miembros por operación.');
        }

        // Interpolación por miembro si la plantilla usa variables.
        // Para el masivo se usa el cuerpo tal cual salvo variables genéricas;
        // cada registro interpola con su objetivo si contiene {{ }}.
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($members, $validated, $campaign, $user, &$created, &$skipped) {
            foreach ($members->chunk(CampaignCommunicationService::BULK_CHUNK) as $chunk) {
                foreach ($chunk as $member) {
                    if ($member->status === 'unsubscribed' || ! DataScope::canViewCampaignMember($user, $member)) {
                        $skipped++;

                        continue;
                    }

                    $target = $member->target();

                    if (! $target) {
                        $skipped++;

                        continue;
                    }

                    $body = str_contains($validated['body'], '{{')
                        ? TemplateVariables::render($validated['body'], TemplateVariables::dataFor($target))
                        : $validated['body'];

                    Communication::create([
                        'campaign_id' => $campaign->id,
                        'campaign_member_id' => $member->id,
                        'contact_id' => $member->member_type === 'contact' ? $member->member_id : null,
                        'lead_id' => $member->member_type === 'lead' ? $member->member_id : null,
                        'template_id' => $validated['template_id'] ?? null,
                        'channel' => $validated['channel'],
                        'direction' => 'outbound',
                        'subject' => $validated['subject'] ?? null,
                        'body' => $body,
                        'status' => 'simulated_sent',
                        'owner_id' => $campaign->owner_id ?? $user->id,
                        'created_by' => $user->id,
                        'sent_at' => now(),
                        'metadata' => [
                            'simulated' => true,
                            'simulated_by' => $user->id,
                            'simulated_at' => now()->toIso8601String(),
                            'bulk_campaign_id' => $campaign->id,
                            'provider' => null,
                        ],
                    ]);

                    if ($member->status === 'pending') {
                        $member->update(['status' => 'sent']);
                    }

                    $created++;
                }
            }
        });

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', "Comunicaciones simuladas: {$created} creada(s), {$skipped} omitida(s). Sin envío externo.");
    }

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        return [
            'campaigns' => Campaign::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::visibleTo($user)->orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name']),
            'leads' => Lead::visibleTo($user)->orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name']),
            'templates' => MessageTemplate::visibleTo($user)->orderBy('name')->get(['id', 'name', 'channel']),
            'owners' => DataScope::filterableUsers($user),
            'channels' => Communication::CHANNELS,
        ];
    }
}
