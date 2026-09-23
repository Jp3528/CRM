<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use App\Support\DataScope;
use App\Support\SyncsTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    use SyncsTags;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contact::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'company_id' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:150'],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Contact::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sort === 'first_name' ? 'contacts.first_name' : "contacts.{$sort}";

        $user = $request->user();

        $contacts = Contact::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'owner:id,name', 'tags:id,name,slug,color',
            ])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->forCompany($validated['company_id'] ?? null)
            ->department($validated['department'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->orderBy($sortColumn, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'company_id' => $validated['company_id'] ?? '',
                'department' => $validated['department'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Contact::STATUSES,
            'companies' => Company::visibleTo($user)->orderBy('trade_name')->get(['id', 'trade_name']),
            'departments' => Contact::visibleTo($user)->select('department')->distinct()
                ->whereNotNull('department')->orderBy('department')->pluck('department'),
            'owners' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Contact::class);
        $preselectedCompanyId = $request->integer('company_id') ?: null;
        if (! DataScope::isVisibleId($request->user(), Company::class, $preselectedCompanyId)) {
            $preselectedCompanyId = null;
        }

        return view('contacts.create', array_merge(
            $this->formData(null),
            ['preselectedCompanyId' => $preselectedCompanyId]
        ));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = DataScope::normalizeOwnerId($request->user(), $data['owner_id'] ?? null);
        DataScope::assertVisibleId($request->user(), Company::class, $data['company_id'] ?? null);
        DataScope::assertCanAssignUser($request->user(), $data['owner_id'] ?? null);

        $contact = Contact::create(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($contact, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Contacto creado correctamente.');
    }

    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $viewer = request()->user();

        $contact->load([
            'company:id,trade_name,legal_name,owner_id',
            'owner:id,name,email',
            'tags:id,name,slug,color',
            'activities' => fn ($q) => $q->visibleTo($viewer)->with('user:id,name')->latest()->limit(10),
            'tasks' => fn ($q) => $q->visibleTo($viewer)->with(['assignee:id,name', 'creator:id,name'])->latest()->limit(10),
            'quotes' => fn ($q) => $q->visibleTo($viewer)->latest()->limit(5),
            'invoices' => fn ($q) => $q->visibleTo($viewer)->latest()->limit(5),
            'tickets' => fn ($q) => $q->visibleTo($viewer)->latest()->limit(5),
        ]);

        $memberCampaignIds = \App\Models\CampaignMember::where('member_type', 'contact')
            ->where('member_id', $contact->id)
            ->pluck('campaign_id')
            ->all();
        $recentCampaigns = $viewer->can('viewAny', \App\Models\Campaign::class) && $memberCampaignIds !== []
            ? \App\Models\Campaign::visibleTo($viewer)->whereKey($memberCampaignIds)->latest()->limit(5)->get()
            : collect();
        $recentCommunications = $viewer->can('viewAny', \App\Models\Communication::class)
            ? \App\Models\Communication::visibleTo($viewer)->where('contact_id', $contact->id)->latest()->limit(5)->get()
            : collect();

        return view('contacts.show', [
            'contact' => $contact,
            'canUpdate' => $viewer->can('update', $contact),
            'canViewCompany' => DataScope::canViewModel($viewer, $contact->company),
            'canViewQuotes' => $viewer->can('viewAny', \App\Models\Quote::class),
            'canViewInvoices' => $viewer->can('viewAny', \App\Models\Invoice::class),
            'canViewTickets' => $viewer->can('viewAny', \App\Models\Ticket::class),
            'recentCampaigns' => $recentCampaigns,
            'recentCommunications' => $recentCommunications,
            'canViewCampaigns' => $viewer->can('viewAny', \App\Models\Campaign::class),
            'canViewCommunications' => $viewer->can('viewAny', \App\Models\Communication::class),
        ]);
    }

    public function edit(Contact $contact): View
    {
        $this->authorize('update', $contact);

        $contact->load('tags:id');

        return view('contacts.edit', array_merge(
            $this->formData($contact),
            ['contact' => $contact]
        ));
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = DataScope::normalizeOwnerId($request->user(), $data['owner_id'] ?? null, $contact->owner_id);
        DataScope::assertVisibleId($request->user(), Company::class, $data['company_id'] ?? null);
        DataScope::assertCanAssignUser($request->user(), $data['owner_id'] ?? null, $contact->owner_id);

        $contact->update(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($contact, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Contacto actualizado correctamente.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Contacto eliminado correctamente.');
    }

    public function detachTag(Contact $contact, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $contact);

        $contact->tags()->detach($tag->id);

        return back()->with('success', 'Etiqueta quitada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Contact $contact): array
    {
        return [
            'owners' => DataScope::filterableUsers(auth()->user(), $contact?->owner_id),
            'companies' => Company::visibleTo(auth()->user())->orderBy('trade_name')->get(['id', 'trade_name']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name']),
            'statuses' => Contact::STATUSES,
        ];
    }
}
