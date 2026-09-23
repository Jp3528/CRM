<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Lead;
use App\Services\Reports\LeadReportService;
use App\Services\Reports\ReportFilters;
use App\Support\DataScope;
use Illuminate\View\View;

class LeadReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Lead::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new LeadReportService($request->user(), $filters);

        return view('reports.leads', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'bySource' => $service->bySource(),
            'byOwner' => $service->byOwner(),
            'scores' => $service->scoreDistribution(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
