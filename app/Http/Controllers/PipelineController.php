<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePipelineRequest;
use App\Http\Requests\UpdatePipelineRequest;
use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Pipeline::class);

        $pipelines = Pipeline::withCount(['stages', 'opportunities'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('settings.pipelines.index', [
            'pipelines' => $pipelines,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Pipeline::class);

        return view('settings.pipelines.create');
    }

    public function store(StorePipelineRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        $pipeline = DB::transaction(function () use ($data, $isDefault) {
            if ($isDefault) {
                Pipeline::where('is_default', true)->update(['is_default' => false]);
            }

            $pipeline = Pipeline::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'status' => $data['status'],
            ]);

            // Crear etapas mínimas requeridas por un pipeline comercial
            $pipeline->stages()->createMany([
                ['name' => 'Prospección', 'position' => 1, 'probability' => 10, 'status' => 'active', 'is_won' => false, 'is_lost' => false],
                ['name' => 'Propuesta', 'position' => 2, 'probability' => 50, 'status' => 'active', 'is_won' => false, 'is_lost' => false],
                ['name' => 'Ganada', 'position' => 3, 'probability' => 100, 'status' => 'active', 'is_won' => true, 'is_lost' => false],
                ['name' => 'Perdida', 'position' => 4, 'probability' => 0, 'status' => 'active', 'is_won' => false, 'is_lost' => true],
            ]);

            return $pipeline;
        });

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', "Pipeline '{$pipeline->name}' creado correctamente con sus etapas base.");
    }

    public function show(Pipeline $pipeline): View
    {
        $this->authorize('view', $pipeline);

        $pipeline->load(['stages' => function ($q) {
            $q->withCount('opportunities')->orderBy('position');
        }]);

        return view('settings.pipelines.show', [
            'pipeline' => $pipeline,
            'stages' => $pipeline->stages,
        ]);
    }

    public function edit(Pipeline $pipeline): View
    {
        $this->authorize('update', $pipeline);

        return view('settings.pipelines.edit', [
            'pipeline' => $pipeline,
        ]);
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        DB::transaction(function () use ($pipeline, $data, $isDefault) {
            if ($isDefault) {
                Pipeline::where('id', '!=', $pipeline->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $pipeline->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'status' => $data['status'],
            ]);
        });

        return redirect()->route('settings.pipelines.show', $pipeline)
            ->with('status', "Pipeline '{$pipeline->name}' actualizado correctamente.");
    }

    public function destroy(Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('delete', $pipeline);

        if ($pipeline->opportunities()->exists()) {
            throw ValidationException::withMessages([
                'pipeline' => 'No es posible eliminar un pipeline que contiene oportunidades comerciales registradas. Puedes archivarlo cambiando su estado a inactivo.',
            ]);
        }

        if ($pipeline->is_default) {
            throw ValidationException::withMessages([
                'pipeline' => 'No es posible eliminar el pipeline predeterminado del sistema. Designa otro como predeterminado primero.',
            ]);
        }

        $name = $pipeline->name;
        $pipeline->delete();

        return redirect()->route('settings.pipelines.index')
            ->with('status', "Pipeline '{$name}' eliminado del sistema.");
    }
}
