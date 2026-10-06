<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Quality\DataQualityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataQualityController extends Controller
{
    public function __construct(
        protected DataQualityService $qualityService = new DataQualityService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Setting::class);

        $actor = $request->user();
        $diagnostics = $this->qualityService->getDiagnostics($actor);

        return view('settings.quality', [
            'metrics' => $diagnostics['metrics'],
            'duplicateEmails' => $diagnostics['duplicate_emails'],
        ]);
    }
}
