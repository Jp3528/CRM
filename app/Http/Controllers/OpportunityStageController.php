<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveOpportunityStageRequest;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class OpportunityStageController extends Controller
{
    /**
     * Mueve la oportunidad de etapa (Kanban drag&drop, formulario de ficha o API).
     * El backend es la autoridad: valida, sincroniza, historía y registra actividad.
     */
    public function update(
        MoveOpportunityStageRequest $request,
        Opportunity $opportunity,
        OpportunityStageService $service
    ): JsonResponse|RedirectResponse {
        $data = $request->validated();

        $result = $service->move(
            $opportunity,
            $request->user(),
            (int) $data['pipeline_stage_id'],
            $data['loss_reason'] ?? null,
            $data['notes'] ?? null
        );

        if ($request->expectsJson() || $request->wantsJson()) {
            $fresh = $result['opportunity'];

            return response()->json([
                'moved' => $result['moved'],
                'opportunity' => [
                    'id' => $fresh->id,
                    'pipeline_stage_id' => $fresh->pipeline_stage_id,
                    'status' => $fresh->status,
                    'probability' => $fresh->probability,
                    'actual_close_date' => $fresh->actual_close_date?->format('Y-m-d'),
                ],
            ]);
        }

        if (! $result['moved']) {
            return back()->with('status', 'La oportunidad ya estaba en esa etapa.');
        }

        $stageName = $result['opportunity']->stage?->name ?? '';

        return back()->with('success', "Oportunidad movida a {$stageName} correctamente.");
    }
}
