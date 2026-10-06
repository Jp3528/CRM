<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePipelineStageRequest;
use App\Http\Requests\UpdatePipelineStageRequest;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\Opportunities\OpportunityStageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PipelineStageController extends Controller
{
    public function __construct(
        protected OpportunityStageService $stageService = new OpportunityStageService,
    ) {}

    public function store(StorePipelineStageRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('update', $pipeline);

        $data = $request->validated();

        if (! empty($data['is_won']) && $pipeline->stages()->where('is_won', true)->exists()) {
            throw ValidationException::withMessages([
                'is_won' => 'El pipeline ya cuenta con una etapa designada como Ganada (100%).',
            ]);
        }

        if (! empty($data['is_lost']) && $pipeline->stages()->where('is_lost', true)->exists()) {
            throw ValidationException::withMessages([
                'is_lost' => 'El pipeline ya cuenta con una etapa designada como Perdida (0%).',
            ]);
        }

        $maxPos = $pipeline->stages()->max('position') ?? 0;
        $position = $data['position'] ?? ($maxPos + 1);

        $pipeline->stages()->create([
            'name' => $data['name'],
            'position' => $position,
            'probability' => $data['probability'],
            'status' => $data['status'],
            'is_won' => (bool) ($data['is_won'] ?? false),
            'is_lost' => (bool) ($data['is_lost'] ?? false),
        ]);

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', "Etapa '{$data['name']}' añadida al pipeline.");
    }

    public function update(UpdatePipelineStageRequest $request, Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        $this->authorize('update', $pipeline);

        if ((int) $stage->pipeline_id !== (int) $pipeline->id) {
            abort(404);
        }

        $data = $request->validated();
        $oldProbability = $stage->probability;
        $newProbability = $data['probability'];

        // Si es etapa ganada o perdida, preservar probabilidad invariante
        if ($stage->is_won) {
            $newProbability = 100;
        } elseif ($stage->is_lost) {
            $newProbability = 0;
        }

        DB::transaction(function () use ($stage, $data, $newProbability, $oldProbability) {
            $stage->update([
                'name' => $data['name'],
                'probability' => $newProbability,
                'status' => $data['status'],
                'position' => $data['position'] ?? $stage->position,
            ]);

            // Sincronizar probabilidades de oportunidades abiertas si cambió
            if ($oldProbability !== $newProbability && ! $stage->is_won && ! $stage->is_lost) {
                $this->stageService->syncOpenOpportunitiesProbability($stage);
            }
        });

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', "Etapa '{$stage->name}' actualizada correctamente.");
    }

    public function destroy(Pipeline $pipeline, PipelineStage $stage): RedirectResponse
    {
        $this->authorize('update', $pipeline);

        if ((int) $stage->pipeline_id !== (int) $pipeline->id) {
            abort(404);
        }

        if ($stage->opportunities()->exists()) {
            throw ValidationException::withMessages([
                'stage' => 'No es posible eliminar una etapa que contiene oportunidades comerciales registradas. Puedes archivarla cambiando su estado a inactivo.',
            ]);
        }

        if ($stage->is_won || $stage->is_lost) {
            throw ValidationException::withMessages([
                'stage' => 'No es posible eliminar la etapa terminal (Ganada o Perdida) del pipeline.',
            ]);
        }

        $name = $stage->name;
        $stage->delete();

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', "Etapa '{$name}' eliminada del pipeline.");
    }

    public function reorder(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('update', $pipeline);

        $validated = $request->validate([
            'stages' => ['required', 'array'],
            'stages.*.id' => ['required', 'integer', 'exists:pipeline_stages,id'],
            'stages.*.position' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($pipeline, $validated) {
            foreach ($validated['stages'] as $item) {
                PipelineStage::where('id', $item['id'])
                    ->where('pipeline_id', $pipeline->id)
                    ->update(['position' => $item['position']]);
            }
        });

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', 'Orden de etapas guardado correctamente.');
    }
}
