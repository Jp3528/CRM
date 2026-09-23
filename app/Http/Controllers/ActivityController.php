<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\User;
use App\Support\DataScope;
use App\Support\RelatedEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'user_id' => ['nullable', 'integer'],
            'related_type' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Activity::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sort === 'scheduled_at' ? 'activities.scheduled_at' : "activities.{$sort}";

        $user = $request->user();

        $activities = Activity::query()
            ->visibleTo($user)
            ->with(['user:id,name'])
            ->search($validated['search'] ?? null)
            ->type($validated['type'] ?? null)
            ->forUser($validated['user_id'] ?? null)
            ->relatedType($validated['related_type'] ?? null)
            ->scheduledBetween($validated['from'] ?? null, $validated['to'] ?? null)
            ->orderBy($sortColumn, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'type' => $validated['type'] ?? '',
                'user_id' => $validated['user_id'] ?? '',
                'related_type' => $validated['related_type'] ?? '',
                'from' => $validated['from'] ?? '',
                'to' => $validated['to'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'types' => array_unique(array_merge(Activity::MANUAL_TYPES, Activity::SYSTEM_TYPES)),
            'relatedTypes' => RelatedEntity::keys(),
            'users' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);

        return view('activities.create', [
            'types' => Activity::MANUAL_TYPES,
            'preselected' => $this->parseRelated($request->query('related')),
            'preselectedType' => in_array($request->query('type'), Activity::MANUAL_TYPES, true)
                ? $request->query('type')
                : null,
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['related_type'])) {
            $related = RelatedEntity::findOrFail($data['related_type'], $data['related_id']);
            abort_unless(DataScope::canViewModel($request->user(), $related), 403);
        }

        $activity = Activity::create([
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'completed_at' => $data['status'] === 'completed' ? now() : null,
            'user_id' => $request->user()->id,
            ...$this->morphAttributes($data),
        ]);

        return redirect()->route('activities.show', $activity)
            ->with('success', 'Actividad registrada correctamente.');
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $viewer = request()->user();

        $activity->load(['user:id,name,email', 'subjectable']);

        return view('activities.show', [
            'activity' => $activity,
            'canUpdate' => ! $activity->is_system && $viewer->can('update', $activity),
            'canDelete' => ! $activity->is_system && $viewer->can('delete', $activity),
            'canViewRelated' => DataScope::canViewModel($viewer, $activity->subjectable),
        ]);
    }

    public function edit(Activity $activity): View|RedirectResponse
    {
        $this->authorize('update', $activity);

        if ($activity->is_system) {
            return redirect()->route('activities.show', $activity)
                ->with('error', 'Las actividades del sistema son trazabilidad y no se pueden editar.');
        }

        return view('activities.edit', [
            'activity' => $activity,
            'types' => Activity::MANUAL_TYPES,
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): RedirectResponse
    {
        $data = $request->validated();

        $activity->update([
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'completed_at' => $data['status'] === 'completed'
                ? ($activity->completed_at ?? now())
                : null,
        ]);

        return redirect()->route('activities.show', $activity)
            ->with('success', 'Actividad actualizada correctamente.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        if ($activity->is_system) {
            abort(403, 'Las actividades del sistema no se pueden eliminar.');
        }

        $activity->delete();

        return redirect()->route('activities.index')
            ->with('success', 'Actividad eliminada correctamente.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function morphAttributes(array $data): array
    {
        if (empty($data['related_type'])) {
            return ['subjectable_type' => null, 'subjectable_id' => null];
        }

        $related = RelatedEntity::findOrFail($data['related_type'], $data['related_id']);

        return ['subjectable_type' => $related::class, 'subjectable_id' => $related->id];
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string}
     */
    private function parseRelated(?string $raw): array
    {
        if (! $raw || ! str_contains($raw, ':')) {
            return ['type' => null, 'id' => null, 'label' => null];
        }

        [$type, $id] = explode(':', $raw, 2);
        $class = RelatedEntity::classFor($type);

        if (! $class || ! is_numeric($id)) {
            return ['type' => null, 'id' => null, 'label' => null];
        }

        $model = $class::find($id);

        return [
            'type' => $model ? $type : null,
            'id' => $model ? (int) $id : null,
            'label' => $model ? RelatedEntity::label($model) : null,
        ];
    }
}
