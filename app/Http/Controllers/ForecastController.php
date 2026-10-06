<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Opportunity;
use App\Services\Reports\ForecastService;
use App\Support\DataScope;
use Illuminate\View\View;

/**
 * Forecast operacional ponderado (solo lectura, sin IA).
 */
class ForecastController extends Controller
{
    public function __invoke(ReportFilterRequest $request): View
    {
        $this->authorize('viewAny', Opportunity::class);
        abort_unless($request->user()->hasPermission('reports.forecast'), 403);

        $filters = ForecastService::defaultFilters($request->validated(), $request->user());
        $service = new ForecastService($request->user(), $filters);

        return view('forecast.index', [
            'filters' => $filters->forView(),
            'forecast' => $service->build(),
            'owners' => DataScope::filterableUsers($request->user(), $filters->ownerId),
        ]);
    }
}
