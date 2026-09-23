<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\User;
use App\Services\Opportunities\OpportunityStageService;
use App\Support\SyncsTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpportunityController extends Controller
{
    use SyncsTags;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Opportunity::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'pipeline_id' => ['nullable', 'integer'],
            'pipeline_stage_id' => ['nullable', 'integer'],
            'owner_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'close_from' => ['nullable', 'date'],
            'close_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Opportunity::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $opportunities = Opportunity::query()
            ->with(['company:id,trade_name', 'contact:id,first_name,last_name', 'pipeline:id,name', 'stage:id,name', 'owner:id,name'])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->pipeline($validated['pipeline_id'] ?? null)
            ->stage($validated['pipeline_stage_id'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->forCompany($validated['company_id'] ?? null)
            ->amountBetween($validated['amount_min'] ?? null, $validated['amount_max'] ?? null)
            ->closeBetween($validated['close_from'] ?? null, $validated['close_to'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('opportunities.index', [
            'opportunities' => $opportunities,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'pipeline_id' => $validated['pipeline_id'] ?? '',
                'pipeline_stage_id' => $validated['pipeline_stage_id'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'company_id' => $validated['company_id'] ?? '',
                'amount_min' => $validated['amount_min'] ?? '',
                'amount_max' => $validated['amount_max'] ?? '',
                'close_from' => $validated['close_from'] ?? '',
                'close_to' => $validated['close_to'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Opportunity::STATUSES,
            'pipelines' => Pipeline::orderBy('name')->get(['id', 'name']),
            'stages' => \App\Models\PipelineStage::with('pipeline:id,name')->orderBy('pipeline_id')->orderBy('position')->get(['id', 'name', 'pipeline_id']),
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
        ]);
    }

    public function kanban(Request $request): View
    {
        $this->authorize('viewAny', Opportunity::class);

        $validated = $request->validate([
            'pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
        ]);

        $pipelines = Pipeline::orderBy('name')->get(['id', 'name', 'is_default']);
        $pipeline = $pipelines->firstWhere('id', $validated['pipeline_id'] ?? null)
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->firstOrFail();

        $stages = $pipeline->stages()->with([
            'opportunities' => fn ($q) => $q->with(['company:id,trade_name', 'owner:id,name'])
                ->orderBy('expected_close_date')->orderBy('id'),
        ])->get();

        $totals = [];
        foreach ($stages as $stage) {
            $totals[$stage->id] = [
                'count' => $stage->opportunities->count(),
                'amount' => $stage->opportunities->sum(fn ($o) => (float) ($o->amount ?? 0)),
            ];
        }

        return view('opportunities.kanban', [
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
            'stages' => $stages,
            'totals' => $totals,
            'canMove' => $request->user()->hasPermission('opportunities.update'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Opportunity::class);

        return view('opportunities.create', $this->formData());
    }

    public function store(StoreOpportunityRequest $request, OpportunityStageService $service): RedirectResponse
    {
        $data = $request->validated();

        $opportunity = $service->create(
            collect($data)->except(['tags', 'new_tags'])->all(),
            $request->user()
        );
        $this->syncTags($opportunity, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Oportunidad creada correctamente.');
    }

    public function show(Opportunity $opportunity): View
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'company:id,trade_name,legal_name',
            'contact:id,first_name,last_name,company_id',
            'lead:id,first_name,last_name,company_name',
            'owner:id,name,email',
            'pipeline:id,name',
            'stage:id,name,probability,is_won,is_lost,pipeline_id',
            'tags:id,name,slug,color',
            'stageHistory' => fn ($q) => $q->with(['fromStage:id,name', 'toStage:id,name', 'changedBy:id,name'])->latest('changed_at'),
            'activities' => fn ($q) => $q->with('user:id,name')->latest()->limit(10),
            'tasks' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name'])->latest()->limit(10),
            'quotes' => fn ($q) => $q->latest()->limit(5),
            'sales' => fn ($q) => $q->latest()->limit(5),
        ]);

        $user = request()->user();

        return view('opportunities.show', [
            'opportunity' => $opportunity,
            'canUpdate' => $user->can('update', $opportunity),
            'canMove' => $user->can('move', $opportunity),
            'canCreateQuote' => $user->can('create', \App\Models\Quote::class),
            'canViewSales' => $user->can('viewAny', \App\Models\Sale::class),
            'pipelineStages' => $opportunity->pipeline->stages()->get(['id', 'name', 'is_won', 'is_lost']),
        ]);
    }

    public function edit(Opportunity $opportunity): View
    {
        $this->authorize('update', $opportunity);

        $opportunity->load(['tags:id', 'stage:id,name', 'pipeline:id,name', 'lead:id,first_name,last_name']);

        return view('opportunities.edit', array_merge(
            $this->formData($opportunity),
            ['opportunity' => $opportunity]
        ));
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        // La etapa/pipeline/status se cambian solo vía OpportunityStageService (move).
        $data = collect($request->validated())->except(['tags', 'new_tags'])->all();

        $opportunity->update($data);
        $this->syncTags($opportunity, $request->validated()['tags'] ?? [], $request->validated()['new_tags'] ?? null);

        // Vincula el contacto si aún no tiene empresa (misma empresa, explícito).
        if ($opportunity->contact && $opportunity->contact->company_id === null && $opportunity->company_id) {
            $opportunity->contact->update(['company_id' => $opportunity->company_id]);
        }

        return redirect()->route('opportunities.show', $opportunity)
            ->with('success', 'Oportunidad actualizada correctamente.');
    }

    public function destroy(Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);

        // Soft delete: no arrastra empresa/contacto/lead ni borra el historial.
        $opportunity->delete();

        return redirect()->route('opportunities.index')
            ->with('success', 'Oportunidad eliminada correctamente.');
    }

    public function detachTag(Opportunity $opportunity, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $opportunity->tags()->detach($tag->id);

        return back()->with('success', 'Etiqueta quitada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Opportunity $opportunity = null): array
    {
        $ownerQuery = User::where('status', 'active')->orderBy('name');
        if ($opportunity?->owner_id) {
            $ownerQuery = User::where(
                fn ($q) => $q->where('status', 'active')->orWhere('id', $opportunity->owner_id)
            )->orderBy('name');
        }

        return [
            'owners' => $ownerQuery->get(['id', 'name']),
            'companies' => Company::orderBy('trade_name')->get(['id', 'trade_name']),
            'contacts' => Contact::with('company:id,trade_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'leads' => Lead::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
            'pipelines' => Pipeline::with('stages:id,name,pipeline_id,probability,is_won,is_lost')->orderBy('name')->get(),
            'allTags' => Tag::orderBy('name')->get(['id', 'name']),
            'currencies' => Opportunity::CURRENCIES,
            'defaultCurrency' => Opportunity::DEFAULT_CURRENCY,
        ];
    }
}
