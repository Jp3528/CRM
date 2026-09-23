<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Opportunity;
use App\Services\Reports\PipelineReportService;
use App\Services\Reports\ReportFilters;
use App\Support\DataScope;
use Illuminate\View\View;

class PipelineReportController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Opportunity::class);
        abort_unless($request->user()->hasPermission('reports.view'), 403);

        $request->validate(['stale_days' => ['nullable', 'integer', 'in:7,14,30,60,90']]);

        $filters = ReportFilters::resolve($request->validated(), $request->user());
        $service = new PipelineReportService($request->user(), $filters);

        return view('reports.pipeline', [
            'filters' => $filters->forView(),
            'summary' => $service->summary(),
            'byStage' => $service->byStage(),
            'buckets' => $service->closeBuckets(),
            'stale' => $service->stale((int) ($request->input('stale_days') ?? 30)),
            'staleDays' => (int) ($request->input('stale_days') ?? 30),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
