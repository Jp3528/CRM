<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use App\Support\SyncsTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    use SyncsTags;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['nullable', 'integer'],
            'score_min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'score_max' => ['nullable', 'integer', 'min:0', 'max:100'],
            'converted' => ['nullable', 'string', 'in:all,yes,no'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Lead::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $leads = Lead::query()
            ->with(['owner:id,name', 'tags:id,name,slug,color'])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->source($validated['source'] ?? null)
            ->ownedBy($validated['owner_id'] ?? null)
            ->scoreBetween($validated['score_min'] ?? null, $validated['score_max'] ?? null)
            ->converted($validated['converted'] ?? null)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'source' => $validated['source'] ?? '',
                'owner_id' => $validated['owner_id'] ?? '',
                'score_min' => $validated['score_min'] ?? '',
                'score_max' => $validated['score_max'] ?? '',
                'converted' => $validated['converted'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Lead::STATUSES,
            'sources' => Lead::SOURCES,
            'owners' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.create', $this->formData(new Lead));
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $lead = Lead::create(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($lead, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Lead creado correctamente.');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        $lead->load([
            'owner:id,name,email',
            'tags:id,name,slug,color',
            'convertedCompany:id,trade_name',
            'convertedContact:id,first_name,last_name',
            'opportunities' => fn ($q) => $q->with('stage:id,name')->latest()->limit(5),
            'activities' => fn ($q) => $q->with('user:id,name')->latest()->limit(10),
            'tasks' => fn ($q) => $q->with(['assignee:id,name', 'creator:id,name'])->latest()->limit(10),
        ]);

        $user = request()->user();

        return view('leads.show', [
            'lead' => $lead,
            'canUpdate' => $user->can('update', $lead),
            'canConvert' => ! $lead->isConverted() && $user->can('convert', $lead),
        ]);
    }

    public function edit(Lead $lead): View|RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->isConverted()) {
            return redirect()->route('leads.show', $lead)
                ->with('error', 'Los leads convertidos son históricos y no se pueden editar.');
        }

        $lead->load('tags:id');

        return view('leads.edit', array_merge(
            $this->formData($lead),
            ['lead' => $lead]
        ));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();

        $lead->update(collect($data)->except(['tags', 'new_tags'])->all());
        $this->syncTags($lead, $data['tags'] ?? [], $data['new_tags'] ?? null);

        return redirect()->route('leads.show', $lead)
            ->with('success', 'Lead actualizado correctamente.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);

        // Soft delete permitido aun convertido: no arrastra empresa/contacto/oportunidad.
        $lead->delete();

        return redirect()->route('leads.index')
            ->with('success', 'Lead eliminado correctamente.');
    }

    public function detachTag(Lead $lead, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $lead);

        $lead->tags()->detach($tag->id);

        return back()->with('success', 'Etiqueta quitada.');
    }

    public function qualify(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        if ($lead->isConverted()) {
            return back()->with('error', 'Un lead convertido no puede recalificarse.');
        }

        if (! in_array($lead->status, ['new', 'contacted'], true)) {
            return back()->with('error', 'Solo se pueden calificar leads nuevos o contactados.');
        }

        $lead->update(['status' => 'qualified']);

        return back()->with('success', 'Lead calificado correctamente. Ya puede convertirse.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Lead $lead): array
    {
        $ownerQuery = User::where('status', 'active')->orderBy('name');
        if ($lead?->owner_id) {
            $ownerQuery = User::where(
                fn ($q) => $q->where('status', 'active')->orWhere('id', $lead->owner_id)
            )->orderBy('name');
        }

        return [
            'owners' => $ownerQuery->get(['id', 'name']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name']),
            'statuses' => Lead::EDITABLE_STATUSES,
            'sources' => Lead::SOURCES,
        ];
    }
}
