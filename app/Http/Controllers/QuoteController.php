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

        $quotes = Quote::query()
            ->with([
                'company:id,trade_name', 'contact:id,first_name,last_name',
                'opportunity:id,name', 'owner:id,name',
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
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'currencies' => Opportunity::CURRENCIES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);

        $prefill = $this->prefillFromOpportunity($request->integer('opportunity_id') ?: null);

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

        $quote->load([
            'company:id,trade_name,legal_name',
            'contact:id,first_name,last_name',
            'opportunity:id,name',
            'owner:id,name,email',
            'items' => fn ($q) => $q->with('product:id,sku,name')->orderBy('position'),
        ]);

        $user = request()->user();

        return view('quotes.show', [
            'quote' => $quote,
            'canUpdate' => $user->can('update', $quote),
            'editable' => in_array($quote->status, Quote::EDITABLE_STATUSES, true),
            'sale' => $quote->sale()->first(['id', 'number', 'status', 'total', 'currency']),
            'canCreateSale' => $user->can('create', \App\Models\Sale::class),
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
            $this->formData(),
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

        $quote->load([
            'company', 'contact', 'opportunity:id,name', 'owner:id,name',
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
    private function prefillFromOpportunity(?int $opportunityId): array
    {
        if (! $opportunityId) {
            return [];
        }

        $opportunity = Opportunity::with(['company:id,trade_name', 'contact:id,first_name,last_name,company_id'])
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
    private function formData(): array
    {
        $products = Product::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price', 'tax_rate']);

        return [
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::with('company:id,trade_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'opportunities' => Opportunity::with('company:id,trade_name')->where('status', 'open')->orderBy('name')->get(['id', 'name', 'company_id', 'currency']),
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
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
