<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\Quotes\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuoteStatusController extends Controller
{
    public function __construct(private QuoteService $service) {}

    public function send(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $updated = $this->service->transition($quote, 'sent', $request->user());

        return back()->with('success', "Cotización {$updated->number} marcada como enviada.");
    }

    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $updated = $this->service->transition($quote, 'accepted', $request->user());

        return back()->with('success', "Cotización {$updated->number} aceptada.");
    }

    public function reject(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $updated = $this->service->transition($quote, 'rejected', $request->user());

        return back()->with('success', "Cotización {$updated->number} rechazada.");
    }
}
