<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Sales\InvoiceCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceStatusController extends Controller
{
    public function __construct(private InvoiceCreationService $service) {}

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $updated = $this->service->transition($invoice, 'sent', $request->user());

        return back()->with('success', "Factura {$updated->number} marcada como enviada. Sin email real.");
    }

    public function pay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $updated = $this->service->transition($invoice, 'paid', $request->user());

        return back()->with('success', "Factura {$updated->number} marcada como pagada (registro interno).");
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $updated = $this->service->transition($invoice, 'cancelled', $request->user());

        return back()->with('success', "Factura {$updated->number} cancelada.");
    }
}
