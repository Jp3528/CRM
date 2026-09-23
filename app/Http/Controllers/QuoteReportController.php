<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Quote;
use App\Services\Reports\QuoteReportService;
use App\Services\Reports\ReportFilters;
use App\Support\DataScope;
use Illuminate\View\View;

class QuoteReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Quote::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new QuoteReportService($request->user(), $filters);

        return view('reports.quotes', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
