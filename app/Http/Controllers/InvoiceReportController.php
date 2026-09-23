<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Invoice;
use App\Services\Reports\InvoiceReportService;
use App\Services\Reports\ReportFilters;
use App\Support\DataScope;
use Illuminate\View\View;

class InvoiceReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new InvoiceReportService($request->user(), $filters);

        return view('reports.invoices', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
