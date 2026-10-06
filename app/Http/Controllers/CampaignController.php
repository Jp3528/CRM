<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Communication;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:30'],
            'owner_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Campaign::SORTABLE, true)
            ? $validated['sort']
            : 'updated_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $contactIds = DataScope::visibleIds($user, Contact::class);
        $leadIds = DataScope::visibleIds($user, Lead::class);
        $contactList = $contactIds === null ? null : ($contactIds === [] ? [0] : $contactIds);
        $leadList = $leadIds === null ? null : ($leadIds === [] ? [0] : $leadIds);

        $campaigns = Campaign::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->withCount([
                'members as scoped_members_count' => function ($q) use ($contactList, $leadList) {
                    // Conteo con alcance: solo miembros cuyo objetivo es visible.
                    if ($contactList === null && $leadList === null) {
                        return;
                    }
                    $q->where(function ($sq) use ($contactList, $leadList) {
                        $hasContact = $contactList !== null;
                        $hasLead = $leadList !== null;
                        if ($hasContact) {
                            $sq->where(function ($w) use ($contactList) {
                                $w->where('campaign_members.member_type', 'contact')
                                    ->whereIn('campaign_members.member_id', $contactList);
                            });
                        }
                        if ($hasLead) {
                            $sq->orWhere(function ($w) use ($leadList) {
                                $w->where('campaign_members.member_type', 'lead')
                                    ->whereIn('campaign_members.member_id', $leadList);
                            });
                        }
                    });
                },
            ])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->type($validated['type'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->dateBetween($validated['from'] ?? null, $validated['to'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('campaigns.index', [
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'type' => $validated['type'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Campaign::STATUSES,
            'types' => Campaign::TYPES,
            'owners' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.create', [
            'owners' => DataScope::filterableUsers(auth()->user()),
            'statuses' => Campaign::STATUSES,
            'types' => Campaign::TYPES,
        ]);
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = DataScope::normalizeOwnerId($request->user(), $data['owner_id'] ?? null);
        DataScope::assertCanAssignUser($request->user(), $data['owner_id'] ?? null);
        $data['status'] ??= 'draft';
        $data['created_by'] = $request->user()->id;

        $campaign = Campaign::create($data);

        $campaign->creator->loadMissing([]);
        Activity::create([
            'type' => 'status_change',
            'subject' => "Campaña creada: {$campaign->name}",
            'description' => "Campaña {$campaign->name} creada en estado {$campaign->status}.",
            'status' => 'completed',
            'user_id' => $request->user()->id,
            'subjectable_type' => Campaign::class,
            'subjectable_id' => $campaign->id,
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña creada correctamente.');
    }

    public function show(Campaign $campaign, Request $request): View
    {
        $this->authorize('view', $campaign);

        $user = $request->user();

        $campaign->load(['owner:id,name,email', 'creator:id,name']);

        $membersQuery = $campaign->members()->orderBy('created_at', 'desc');
        DataScope::scopeCampaignMembers($membersQuery, $user);
        $members = $membersQuery->paginate(20, ['*'], 'members_page')->withQueryString();
        $this->attachTargets($members->getCollection(), $user);

        $contactIds = DataScope::visibleIds($user, Contact::class);
        $leadIds = DataScope::visibleIds($user, Lead::class);
        $scopedCount = $campaign->members()
            ->when(true, function ($q) use ($user) {
                DataScope::scopeCampaignMembers($q, $user);
            })->count();

        $communications = Communication::visibleTo($user)
            ->forCampaign($campaign->id)
            ->with(['contact:id,first_name,last_name,owner_id', 'lead:id,first_name,last_name,owner_id', 'template:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        $activities = Activity::visibleTo($user)
            ->where('subjectable_type', Campaign::class)
            ->where('subjectable_id', $campaign->id)
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return view('campaigns.show', [
            'campaign' => $campaign,
            'members' => $members,
            'scopedMembersCount' => $scopedCount,
            'communications' => $communications,
            'activities' => $activities,
            'canUpdate' => $user->can('update', $campaign),
            'canManageMembers' => $user->can('update', $campaign) && $campaign->isEditable(),
            'canCommunicate' => $user->can('create', Communication::class),
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'owners' => DataScope::filterableUsers(auth()->user(), $campaign->owner_id),
            'statuses' => Campaign::STATUSES,
            'types' => Campaign::TYPES,
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $data['owner_id'] = DataScope::normalizeOwnerId($actor, $data['owner_id'] ?? null, $campaign->owner_id);
        DataScope::assertCanAssignUser($actor, $data['owner_id'] ?? null, $campaign->owner_id);

        $before = $campaign->status;
        $campaign->update($data);

        if ($before !== $campaign->status && in_array($campaign->status, ['active', 'completed'], true)) {
            $label = $campaign->status === 'active' ? 'activada' : 'completada';
            Activity::create([
                'type' => 'status_change',
                'subject' => "Campaña {$label}: {$campaign->name}",
                'description' => "Campaña {$campaign->name} pasó de {$before} a {$campaign->status}.",
                'status' => 'completed',
                'user_id' => $actor->id,
                'subjectable_type' => Campaign::class,
                'subjectable_id' => $campaign->id,
            ]);
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('success', 'Campaña actualizada correctamente.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaña eliminada correctamente.');
    }

    /**
     * Hidrata objetivos visibles en una colección de miembros (evita N+1).
     *
     * @param  Collection<int, CampaignMember>  $members
     */
    private function attachTargets(Collection $members, User $user): void
    {
        $contactIds = $members->where('member_type', 'contact')->pluck('member_id')->unique()->all();
        $leadIds = $members->where('member_type', 'lead')->pluck('member_id')->unique()->all();

        $contacts = $contactIds === []
            ? collect()
            : Contact::visibleTo($user)->whereKey($contactIds)->get()->keyBy('id');
        $leads = $leadIds === []
            ? collect()
            : Lead::visibleTo($user)->whereKey($leadIds)->get()->keyBy('id');

        foreach ($members as $member) {
            $member->setRelation(
                'target_model',
                $member->member_type === 'contact'
                    ? $contacts->get($member->member_id)
                    : $leads->get($member->member_id)
            );
        }
    }
}
