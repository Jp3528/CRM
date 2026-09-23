<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\Tag;
use App\Models\User;
use App\Support\SyncsTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    use SyncsTags;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Company::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'industry' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Company::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $companies = Company::query()
            ->with(['owner:id,name', 'tags:id,name,slug,color'])
            ->withCount('contacts')
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->industry($validated['industry'] ?? null)
            ->country($validated['country'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('companies.index', [
            'companies' => $companies,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'industry' => $validated['industry'] ?? '',
                'country' => $validated['country'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Company::STATUSES,
            'industries' => Company::query()->select('industry')->distinct()
                ->whereNotNull('industry')->orderBy('industry')->pluck('industry'),
            'countries' => Company::query()->select('country')->distinct()
                ->whereNotNull('country')->orderBy('country')->pluck('country'),
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Company::class);

        return view('companies.create', $this->formData(new Company));
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $company = Company::create(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($company, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Empresa creada correctamente.');
    }

    public function show(Company $company): View
    {
        $this->authorize('view', $company);

        $company->load([
            'owner:id,name,email',
            'tags:id,name,slug,color',
            'contacts' => fn ($q) => $q->with('owner:id,name')->orderBy('first_name'),
            'activities' => fn ($q) => $q->with('user:id,name')->latest()->limit(10),
            'tasks' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name'])->latest()->limit(10),
            'quotes' => fn ($q) => $q->latest()->limit(5),
            'sales' => fn ($q) => $q->latest()->limit(5),
            'invoices' => fn ($q) => $q->latest()->limit(5),
            'tickets' => fn ($q) => $q->latest()->limit(5),
        ])->loadCount('contacts');

        return view('companies.show', [
            'company' => $company,
            'canUpdate' => request()->user()->can('update', $company),
            'canCreateContact' => request()->user()->can('create', \App\Models\Contact::class),
            'canViewQuotes' => request()->user()->can('viewAny', \App\Models\Quote::class),
            'canViewSales' => request()->user()->can('viewAny', \App\Models\Sale::class),
            'canViewInvoices' => request()->user()->can('viewAny', \App\Models\Invoice::class),
            'canViewTickets' => request()->user()->can('viewAny', \App\Models\Ticket::class),
        ]);
    }

    public function edit(Company $company): View
    {
        $this->authorize('update', $company);

        $company->load('tags:id');

        return view('companies.edit', array_merge(
            $this->formData($company),
            ['company' => $company]
        ));
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $data = $request->validated();

        $company->update(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($company, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('companies.show', $company)
            ->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Empresa eliminada correctamente.');
    }

    public function detachTag(Company $company, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->tags()->detach($tag->id);

        return back()->with('success', 'Etiqueta quitada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Company $company): array
    {
        $ownerQuery = User::where('status', 'active')->orderBy('name');
        if ($company?->owner_id) {
            $ownerQuery = User::where(
                fn ($q) => $q->where('status', 'active')->orWhere('id', $company->owner_id)
            )->orderBy('name');
        }

        return [
            'owners' => $ownerQuery->get(['id', 'name']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name']),
            'statuses' => Company::STATUSES,
        ];
    }
}
