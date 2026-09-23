<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
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

        $contacts = Contact::query()
            ->with(['company:id,trade_name', 'owner:id,name', 'tags:id,name,slug,color'])
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
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'departments' => Contact::query()->select('department')->distinct()
                ->whereNotNull('department')->orderBy('department')->pluck('department'),
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Contact::class);

        return view('contacts.create', array_merge(
            $this->formData(null),
            ['preselectedCompanyId' => $request->integer('company_id') ?: null]
        ));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $contact = Contact::create(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($contact, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('contacts.show', $contact)
            ->with('success', 'Contacto creado correctamente.');
    }

    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $contact->load([
            'company:id,trade_name,legal_name',
            'owner:id,name,email',
            'tags:id,name,slug,color',
            'activities' => fn ($q) => $q->with('user:id,name')->latest()->limit(10),
            'tasks' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name'])->latest()->limit(10),
            'quotes' => fn ($q) => $q->latest()->limit(5),
            'invoices' => fn ($q) => $q->latest()->limit(5),
            'tickets' => fn ($q) => $q->latest()->limit(5),
        ]);

        return view('contacts.show', [
            'contact' => $contact,
            'canUpdate' => request()->user()->can('update', $contact),
            'canViewQuotes' => request()->user()->can('viewAny', \App\Models\Quote::class),
            'canViewInvoices' => request()->user()->can('viewAny', \App\Models\Invoice::class),
            'canViewTickets' => request()->user()->can('viewAny', \App\Models\Ticket::class),
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
        $ownerQuery = User::where('status', 'active')->orderBy('name');
        if ($contact?->owner_id) {
            $ownerQuery = User::where(
                fn ($q) => $q->where('status', 'active')->orWhere('id', $contact->owner_id)
            )->orderBy('name');
        }

        return [
            'owners' => $ownerQuery->get(['id', 'name']),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name']),
            'statuses' => Contact::STATUSES,
        ];
    }
}
