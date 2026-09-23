<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

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

        $sort = in_array($validated['sort'] ?? '', Invoice::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $invoices = Invoice::query()
            ->visibleTo($user)
            ->with([
                'sale:id,number', 'company:id,trade_name', 'contact:id,first_name,last_name',
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

        return view('invoices.index', [
            'invoices' => $invoices,
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
            'statuses' => Invoice::STATUSES,
            'owners' => DataScope::filterableUsers($user),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'currencies' => Opportunity::CURRENCIES,
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'sale:id,number,status',
            'company:id,trade_name,legal_name',
            'contact:id,first_name,last_name',
            'owner:id,name,email',
            'items' => fn ($q) => $q->with('product:id,sku,name')->orderBy('position'),
        ]);

        $user = request()->user();

        return view('invoices.show', [
            'invoice' => $invoice,
            'canUpdate' => $user->can('update', $invoice),
            'canViewCompany' => DataScope::canViewModel($user, $invoice->company),
            'canViewContact' => DataScope::canViewModel($user, $invoice->contact),
            'canViewSale' => DataScope::canViewModel($user, $invoice->sale),
        ]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Factura eliminada correctamente.');
    }

    public function print(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'company', 'contact', 'owner:id,name', 'sale:id,number',
            'items' => fn ($q) => $q->orderBy('position'),
        ]);

        return view('invoices.print', ['invoice' => $invoice]);
    }
}
