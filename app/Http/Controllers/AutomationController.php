<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAutomationRequest;
use App\Http\Requests\UpdateAutomationRequest;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\User;
use App\Support\AutomationCatalog;
use App\Support\AutomationDefinitionValidator;
use App\Support\ConditionEvaluator;
use App\Support\DataScope;
use App\Support\RelatedEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Automation::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'trigger_type' => ['nullable', 'string', 'max:60'],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Automation::SORTABLE, true)
            ? $validated['sort']
            : 'updated_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $automations = Automation::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->withCount('runs')
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->trigger($validated['trigger_type'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('automations.index', [
            'automations' => $automations,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'trigger_type' => $validated['trigger_type'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Automation::STATUSES,
            'triggers' => AutomationCatalog::TRIGGERS,
            'owners' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Automation::class);

        return view('automations.create', $this->builderData());
    }

    public function store(StoreAutomationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // conditions/actions llegan como input crudo: el validador central
        // (whitelist estricta) es la autoridad, no las rules superficiales.
        try {
            $checked = AutomationDefinitionValidator::validate([
                'trigger_type' => $data['trigger_type'],
                'owner_id' => $data['owner_id'] ?? null,
                'conditions' => $request->input('conditions', []),
                'actions' => $request->input('actions', []),
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $automation = Automation::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'trigger_type' => $data['trigger_type'],
            'conditions' => $checked['conditions'],
            'actions' => $checked['actions'],
            'owner_id' => $checked['owner']->id,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('automations.show', $automation)
            ->with('success', 'Automatización creada en borrador.');
    }

    public function show(Automation $automation): View
    {
        $this->authorize('view', $automation);

        $user = request()->user();
        $automation->load(['owner:id,name,email', 'creator:id,name']);

        $runs = AutomationRun::where('automation_id', $automation->id)
            ->with('triggerer:id,name')
            ->latest()
            ->paginate(15, ['*'], 'runs_page')
            ->withQueryString();

        return view('automations.show', [
            'automation' => $automation,
            'runs' => $runs,
            'canUpdate' => $user->can('update', $automation),
            'canExecute' => $user->can('execute', $automation),
            'triggerLabel' => AutomationCatalog::triggerLabel($automation->trigger_type),
            'fieldTypes' => AutomationCatalog::fieldsForTrigger($automation->trigger_type),
        ]);
    }

    public function edit(Automation $automation): View
    {
        $this->authorize('update', $automation);

        abort_unless($automation->isEditable(), 422, 'Pausa la automatización antes de editarla.');

        return view('automations.edit', array_merge(
            $this->builderData($automation),
            ['automation' => $automation]
        ));
    }

    public function update(UpdateAutomationRequest $request, Automation $automation): RedirectResponse
    {
        abort_unless($automation->isEditable(), 422, 'Pausa la automatización antes de editarla.');

        $data = $request->validated();

        try {
            $checked = AutomationDefinitionValidator::validate([
                'trigger_type' => $data['trigger_type'],
                'owner_id' => $data['owner_id'] ?? null,
                'conditions' => $request->input('conditions', []),
                'actions' => $request->input('actions', []),
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $automation->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'],
            'conditions' => $checked['conditions'],
            'actions' => $checked['actions'],
            'owner_id' => $checked['owner']->id,
        ]);

        return redirect()->route('automations.show', $automation)
            ->with('success', 'Automatización actualizada correctamente.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $this->authorize('delete', $automation);

        if ($automation->status === 'active') {
            return back()->with('error', 'Pausa la automatización antes de eliminarla.');
        }

        $automation->delete();

        return redirect()->route('automations.index')
            ->with('success', 'Automatización eliminada correctamente (historial conservado).');
    }

    public function activate(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorize('execute', $automation);

        abort_unless(in_array($automation->status, ['draft', 'paused'], true), 422, 'Solo borrador o pausada pueden activarse.');

        // Revalidación total al activar: nunca se confía en la config guardada.
        try {
            AutomationDefinitionValidator::validate([
                'trigger_type' => $automation->trigger_type,
                'conditions' => $automation->conditions ?? [],
                'actions' => $automation->actions ?? [],
                'owner_id' => $automation->owner_id,
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())
                ->with('error', 'La configuración ya no es válida y no puede activarse.');
        }

        if (! $automation->owner || $automation->owner->status !== 'active') {
            return back()->with('error', 'El responsable está inactivo; no se puede activar.');
        }

        $automation->update(['status' => 'active']);

        return back()->with('success', 'Automatización activada: escuchará su trigger.');
    }

    public function pause(Automation $automation): RedirectResponse
    {
        $this->authorize('execute', $automation);

        abort_unless($automation->status === 'active', 422, 'Solo una automatización activa puede pausarse.');

        $automation->update(['status' => 'paused']);

        return back()->with('success', 'Automatización pausada: no ejecutará nuevas acciones.');
    }

    /**
     * Dry run: evalúa compatibilidad + condiciones y muestra el plan,
     * sin modificar la BD (sin tareas, actividades, miembros ni runs).
     */
    public function dryRun(Request $request, Automation $automation): View
    {
        $this->authorize('execute', $automation);

        $validated = $request->validate([
            'subject_type' => ['required', 'string', 'max:30'],
            'subject_id' => ['required', 'integer', 'min:1'],
        ]);

        $expectedSubject = AutomationCatalog::subjectFor($automation->trigger_type);
        $compatible = $validated['subject_type'] === $expectedSubject;

        $subject = null;
        $conditionsMet = null;
        $plan = [];

        if ($compatible) {
            $class = AutomationCatalog::classForSubject($validated['subject_type']);
            abort_unless($class !== null, 422, 'Tipo de registro no permitido.');
            DataScope::assertVisibleId($request->user(), $class, $validated['subject_id']);

            $subject = $class::findOrFail($validated['subject_id']);

            $context = $this->dryContext($automation->trigger_type, $subject);
            $fieldTypes = AutomationCatalog::fieldsForTrigger($automation->trigger_type);
            $conditionsMet = ConditionEvaluator::passes($automation->conditions ?? [], $fieldTypes, $context);

            if ($conditionsMet) {
                $plan = $this->dryPlan($automation, $subject, $validated['subject_type'], $request->user());
            }
        }

        return view('automations.dry-run', [
            'automation' => $automation,
            'subjectType' => $validated['subject_type'],
            'subjectId' => $validated['subject_id'],
            'subject' => $subject,
            'compatible' => $compatible,
            'expectedSubject' => $expectedSubject,
            'conditionsMet' => $conditionsMet,
            'plan' => $plan,
        ]);
    }

    public function showRun(Automation $automation, AutomationRun $run): View
    {
        $this->authorize('view', $automation);

        abort_unless((int) $run->automation_id === (int) $automation->id, 404);

        $run->load(['triggerer:id,name', 'automation:id,name']);

        return view('automations.run-show', [
            'automation' => $automation,
            'run' => $run,
        ]);
    }

    /** @return array<string, mixed> */
    private function builderData(?Automation $automation = null): array
    {
        $user = auth()->user();

        return [
            'triggers' => AutomationCatalog::TRIGGERS,
            'owners' => DataScope::filterableUsers($user, $automation?->owner_id),
            'catalog' => [
                'triggers' => AutomationCatalog::TRIGGERS,
                'fields' => $this->allTriggerFields(),
                'operators' => AutomationCatalog::OPERATORS_BY_TYPE,
                'actions' => AutomationCatalog::ACTIONS,
                'maxConditions' => AutomationCatalog::MAX_CONDITIONS,
                'maxActions' => AutomationCatalog::MAX_ACTIONS,
            ],
        ];
    }

    /** @return array<string, array<string, string>> */
    private function allTriggerFields(): array
    {
        $out = [];

        foreach (AutomationCatalog::triggers() as $trigger) {
            $out[$trigger] = AutomationCatalog::fieldsForTrigger($trigger);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function dryContext(string $trigger, Model $subject): array
    {
        // Contexto actual del registro (previous_* quedan null en dry run).
        $context = [];
        foreach (AutomationCatalog::fieldsForTrigger($trigger) as $field => $type) {
            $context[$field] = match ($field) {
                'status' => $subject->status ?? null,
                'source' => $subject->source ?? null,
                'score' => $subject->score !== null ? (int) $subject->score : null,
                'estimated_value' => $subject->estimated_value !== null ? (string) $subject->estimated_value : null,
                'owner_id' => $subject->owner_id !== null ? (int) $subject->owner_id : null,
                'company_id' => $subject->company_id !== null ? (int) $subject->company_id : null,
                'stage_id' => $subject->pipeline_stage_id !== null ? (int) $subject->pipeline_stage_id : null,
                'amount' => $subject->amount !== null ? (string) $subject->amount : null,
                'probability' => $subject->probability !== null ? (int) $subject->probability : null,
                'priority' => $subject->priority ?? null,
                'assigned_to' => $subject->assigned_to !== null ? (int) $subject->assigned_to : null,
                'category_id' => $subject->category_id !== null ? (int) $subject->category_id : null,
                'total' => $subject->total !== null ? (string) $subject->total : null,
                'type' => $subject->type ?? null,
                default => null,
            };
        }

        return $context;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function dryPlan(Automation $automation, Model $subject, string $subjectKey, User $viewer): array
    {
        $plan = [];

        foreach ($automation->actions ?? [] as $action) {
            $type = $action['type'] ?? 'desconocida';
            $detail = match ($type) {
                'create_task' => 'Tarea "'.$action['title'].'" (prioridad '.$action['priority'].', +'.$action['due_in_days'].' días, para '.$action['assigned_to_mode'].')'
                    .($subjectKey && RelatedEntity::classFor($subjectKey) ? ' relacionada al registro.' : ' sin relación.'),
                'create_activity' => 'Actividad '.$action['activity_type'].' "'.$action['subject'].'" relacionada al registro.',
                'assign_owner' => 'Reasignar responsable a '.$action['target_mode'].'.',
                'add_to_campaign' => 'Agregar a campaña #'.$action['campaign_id'].'.',
                default => 'Acción desconocida.',
            };

            $plan[] = ['action' => $type, 'detail' => $detail];
        }

        return $plan;
    }
}
