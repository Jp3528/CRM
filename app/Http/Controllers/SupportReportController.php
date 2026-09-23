<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Ticket;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\SupportReportService;
use App\Support\DataScope;
use Illuminate\View\View;

class SupportReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new SupportReportService($request->user(), $filters);

        return view('reports.support', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'breakdowns' => $service->breakdowns(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
