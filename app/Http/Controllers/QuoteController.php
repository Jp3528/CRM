<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Services\Quotes\QuoteService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'owner_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'max:3'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Quote::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $quotes = Quote::query()
            ->visibleTo($user)
            ->with([
                'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'owner_id'),
                'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
                'opportunity' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
                'owner:id,name',
            ])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->forCompany($validated['company_id'] ?? null)
            ->currency($validated['currency'] ?? null)
            ->issuedBetween($validated['issued_from'] ?? null, $validated['issued_to'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('quotes.index', [
            'quotes' => $quotes,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'company_id' => $validated['company_id'] ?? '',
                'currency' => $validated['currency'] ?? '',
                'issued_from' => $validated['issued_from'] ?? '',
                'issued_to' => $validated['issued_to'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Quote::STATUSES,
            'owners' => DataScope::filterableUsers($user),
            'companies' => Company::visibleTo($user)->orderBy('trade_name')->get(['id', 'trade_name']),
            'currencies' => Opportunity::CURRENCIES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);

        $prefill = $this->prefillFromOpportunity($request->user(), $request->integer('opportunity_id') ?: null);

        return view('quotes.create', array_merge(
            $this->formData(),
            ['prefill' => $prefill]
        ));
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $quote = $this->service->create($request->validated(), $request->user());

        return redirect()->route('quotes.show', $quote)
            ->with('success', "Cotización {$quote->number} creada correctamente.");
    }

    public function show(Quote $quote): View
    {
        $this->authorize('view', $quote);
        $user = request()->user();

        $quote->load([
            'company' => fn ($q) => $q->visibleTo($user)->select('id', 'trade_name', 'legal_name', 'owner_id'),
            'contact' => fn ($q) => $q->visibleTo($user)->select('id', 'first_name', 'last_name', 'company_id', 'owner_id'),
            'opportunity' => fn ($q) => $q->visibleTo($user)->select('id', 'name', 'owner_id'),
            'owner:id,name,email',
            'items' => fn ($q) => $q->with('product:id,sku,name')->orderBy('position'),
        ]);

        return view('quotes.show', [
            'quote' => $quote,
            'canUpdate' => $user->can('update', $quote),
            'editable' => in_array($quote->status, Quote::EDITABLE_STATUSES, true),
            'sale' => $quote->sale()->visibleTo($user)->first(['id', 'number', 'status', 'total', 'currency']),
            'canCreateSale' => $user->can('create', \App\Models\Sale::class),
            'canViewCompany' => DataScope::canViewModel($user, $quote->company),
            'canViewContact' => DataScope::canViewModel($user, $quote->contact),
            'canViewOpportunity' => DataScope::canViewModel($user, $quote->opportunity),
        ]);
    }

    public function edit(Quote $quote): View|RedirectResponse
    {
        $this->authorize('update', $quote);

        if (! in_array($quote->status, Quote::EDITABLE_STATUSES, true)) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Solo se pueden editar cotizaciones en borrador o enviadas.');
        }

        $quote->load(['items' => fn ($q) => $q->orderBy('position')]);

        return view('quotes.edit', array_merge(
            $this->formData($quote),
            ['quote' => $quote]
        ));
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $updated = $this->service->update($quote, $request->validated(), $request->user());

        return redirect()->route('quotes.show', $updated)
            ->with('success', "Cotización {$updated->number} actualizada correctamente.");
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', 'Cotización eliminada correctamente.');
    }

    public function print(Quote $quote): View
    {
        $this->authorize('view', $quote);
        $user = request()->user();

        $quote->load([
            'company' => fn ($q) => $q->visibleTo($user),
            'contact' => fn ($q) => $q->visibleTo($user),
            'opportunity' => fn ($q) => $q->visibleTo($user)->select('id', 'name'),
            'owner:id,name',
            'items' => fn ($q) => $q->orderBy('position'),
        ]);

        return view('quotes.print', ['quote' => $quote]);
    }

    /**
     * Prellena desde una oportunidad (?opportunity=ID): empresa, contacto,
     * oportunidad, responsable y moneda. Sin líneas ficticias por amount.
     *
     * @return array<string, mixed>
     */
    private function prefillFromOpportunity(User $user, ?int $opportunityId): array
    {
        if (! $opportunityId) {
            return [];
        }

        $opportunity = Opportunity::visibleTo($user)
            ->with(['company:id,trade_name', 'contact:id,first_name,last_name,company_id'])
            ->find($opportunityId);

        if (! $opportunity) {
            return [];
        }

        return [
            'company_id' => $opportunity->company_id,
            'contact_id' => $opportunity->contact_id,
            'opportunity_id' => $opportunity->id,
            'owner_id' => $opportunity->owner_id,
            'currency' => $opportunity->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Quote $quote = null): array
    {
        $products = Product::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price', 'tax_rate']);

        return [
            'companies' => Company::visibleTo(auth()->user())->orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::visibleTo(auth()->user())
                ->with(['company' => fn ($q) => $q->visibleTo(auth()->user())->select('id', 'trade_name', 'owner_id')])
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'opportunities' => Opportunity::visibleTo(auth()->user())
                ->with(['company' => fn ($q) => $q->visibleTo(auth()->user())->select('id', 'trade_name', 'owner_id')])
                ->where('status', 'open')->orderBy('name')->get(['id', 'name', 'company_id', 'currency']),
            'owners' => DataScope::filterableUsers(auth()->user(), $quote?->owner_id),
            'currencies' => Opportunity::CURRENCIES,
            'products' => $products,
            'productCatalog' => $products->mapWithKeys(fn ($p) => [$p->id => [
                'name' => $p->name, 'unit' => $p->unit, 'price' => $p->price, 'tax' => $p->tax_rate,
            ]])->all(),
            'units' => Product::UNITS,
            'discountTypes' => QuoteItem::DISCOUNT_TYPES,
        ];
    }
}
