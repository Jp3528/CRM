<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\Sales\SaleCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleStatusController extends Controller
{
    public function __construct(private SaleCreationService $service) {}

    public function confirm(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $updated = $this->service->transition($sale, 'confirmed', $request->user());

        return back()->with('success', "Venta {$updated->number} confirmada.");
    }

    public function complete(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $updated = $this->service->transition($sale, 'completed', $request->user());

        return back()->with('success', "Venta {$updated->number} completada.");
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $updated = $this->service->transition($sale, 'cancelled', $request->user());

        return back()->with('success', "Venta {$updated->number} cancelada.");
    }
}
