<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Automation;
use App\Services\Reports\AutomationReportService;
use App\Services\Reports\ReportFilters;
use App\Support\DataScope;
use Illuminate\View\View;

class AutomationReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Automation::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new AutomationReportService($request->user(), $filters);

        return view('reports.automations', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
