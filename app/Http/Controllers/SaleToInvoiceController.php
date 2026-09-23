<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertSaleToInvoiceRequest;
use App\Models\Sale;
use App\Services\Sales\InvoiceCreationService;
use App\Support\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SaleToInvoiceController extends Controller
{
    public function __construct(private InvoiceCreationService $service) {}

    public function create(Sale $sale): View|RedirectResponse
    {
        $this->authorize('create', \App\Models\Invoice::class);
        abort_unless(DataScope::canViewModel(request()->user(), $sale), 403);

        $sale->load(['company:id,trade_name', 'items' => fn ($q) => $q->orderBy('position')]);

        if (! in_array($sale->status, ['confirmed', 'completed'], true)) {
            return redirect()->route('sales.show', $sale)
                ->with('error', 'Solo se pueden facturar ventas confirmadas o completadas.');
        }

        if ($sale->invoice()->exists()) {
            return redirect()->route('invoices.show', $sale->invoice)
                ->with('status', 'Esta venta ya generó una factura.');
        }

        return view('sales.convert-to-invoice', ['sale' => $sale]);
    }

    public function store(ConvertSaleToInvoiceRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('create', \App\Models\Invoice::class);
        abort_unless(DataScope::canViewModel($request->user(), $sale), 403);

        $invoice = $this->service->fromSale($sale, $request->user(), $request->validated());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura {$invoice->number} generada desde venta {$sale->number}. Documento interno, no fiscal.");
    }
}
