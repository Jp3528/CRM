<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Sale;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\SalesReportService;
use App\Support\DataScope;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Sale::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new SalesReportService($request->user(), $filters);

        return view('reports.sales', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'trend' => $service->trend(),
            'byOwner' => $service->byOwner(),
            'byCompany' => $service->byCompany(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
