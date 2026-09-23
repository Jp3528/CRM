<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertQuoteToSaleRequest;
use App\Models\Quote;
use App\Services\Sales\SaleCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuoteToSaleController extends Controller
{
    public function __construct(private SaleCreationService $service) {}

    public function create(Quote $quote): View|RedirectResponse
    {
        $this->authorize('create', \App\Models\Sale::class);

        $quote->load(['company:id,trade_name', 'items' => fn ($q) => $q->orderBy('position')]);

        if ($quote->status !== 'accepted') {
            return redirect()->route('quotes.show', $quote)
                ->with('error', 'Solo se pueden convertir cotizaciones aceptadas.');
        }

        if ($quote->sale()->exists()) {
            return redirect()->route('sales.show', $quote->sale)
                ->with('status', 'Esta cotización ya generó una venta.');
        }

        return view('quotes.convert-to-sale', [
            'quote' => $quote,
            'owners' => \App\Models\User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ConvertQuoteToSaleRequest $request, Quote $quote): RedirectResponse
    {
        $this->authorize('create', \App\Models\Sale::class);

        $sale = $this->service->fromQuote($quote, $request->user(), $request->validated());

        return redirect()->route('sales.show', $sale)
            ->with('success', "Venta {$sale->number} creada desde cotización {$quote->number}.");
    }
}
