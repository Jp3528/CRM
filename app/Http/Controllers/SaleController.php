<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateSaleRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleCreationService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(private SaleCreationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Sale::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'owner_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'max:3'],
            'sold_from' => ['nullable', 'date'],
            'sold_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Sale::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $sales = Sale::query()
            ->visibleTo($user)
            ->with([
                'company:id,trade_name', 'contact:id,first_name,last_name',
                'opportunity:id,name', 'quote:id,number', 'owner:id,name',
            ])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->forCompany($validated['company_id'] ?? null)
            ->currency($validated['currency'] ?? null)
            ->soldBetween($validated['sold_from'] ?? null, $validated['sold_to'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'company_id' => $validated['company_id'] ?? '',
                'currency' => $validated['currency'] ?? '',
                'sold_from' => $validated['sold_from'] ?? '',
                'sold_to' => $validated['sold_to'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Sale::STATUSES,
            'owners' => DataScope::filterableUsers($user),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'currencies' => Opportunity::CURRENCIES,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Sale::class);

        return view('sales.create', $this->formData());
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $sale = $this->service->createManual($request->validated(), $request->user());

        return redirect()->route('sales.show', $sale)
            ->with('success', "Venta {$sale->number} creada correctamente.");
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load([
            'quote:id,number,status',
            'company:id,trade_name,legal_name',
            'contact:id,first_name,last_name',
            'opportunity:id,name',
            'owner:id,name,email',
            'invoice:id,number,status,total,currency',
            'items' => fn ($q) => $q->with('product:id,sku,name')->orderBy('position'),
        ]);

        $user = request()->user();

        return view('sales.show', [
            'sale' => $sale,
            'canUpdate' => $user->can('update', $sale),
            'editable' => $sale->status === 'draft',
            'quoteSourced' => $sale->quote_id !== null,
            'canCreateInvoice' => $user->can('create', \App\Models\Invoice::class),
            'canViewCompany' => DataScope::canViewModel($user, $sale->company),
            'canViewContact' => DataScope::canViewModel($user, $sale->contact),
            'canViewOpportunity' => DataScope::canViewModel($user, $sale->opportunity),
            'canViewQuote' => DataScope::canViewModel($user, $sale->quote),
        ]);
    }

    public function edit(Sale $sale): View|RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($sale->status !== 'draft') {
            return redirect()->route('sales.show', $sale)
                ->with('error', 'Solo se pueden editar ventas en borrador.');
        }

        $sale->load(['items' => fn ($q) => $q->orderBy('position')]);

        return view('sales.edit', array_merge(
            $this->formData($sale),
            ['sale' => $sale]
        ));
    }

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        $updated = $this->service->update($sale, $request->validated(), $request->user());

        return redirect()->route('sales.show', $updated)
            ->with('success', "Venta {$updated->number} actualizada correctamente.");
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', 'Venta eliminada correctamente.');
    }

    public function print(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load([
            'company', 'contact', 'opportunity:id,name', 'owner:id,name',
            'items' => fn ($q) => $q->orderBy('position'),
        ]);

        return view('sales.print', ['sale' => $sale]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Sale $sale = null): array
    {
        return [
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::with('company:id,trade_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'opportunities' => Opportunity::with('company:id,trade_name')->where('status', 'open')->orderBy('name')->get(['id', 'name', 'company_id']),
            'owners' => DataScope::filterableUsers(auth()->user(), $sale?->owner_id),
            'currencies' => Opportunity::CURRENCIES,
            'products' => Product::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'price', 'tax_rate']),
            'productCatalog' => Product::where('status', 'active')->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => [
                'name' => $p->name, 'unit' => $p->unit, 'price' => $p->price, 'tax' => $p->tax_rate,
            ]])->all(),
            'units' => Product::UNITS,
            'discountTypes' => QuoteItem::DISCOUNT_TYPES,
        ];
    }
}
