<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Support\DataScope;
use App\Support\RelatedEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:30'],
            'assigned_to' => ['nullable', 'integer'],
            'related_type' => ['nullable', 'string', 'max:30'],
            'due' => ['nullable', 'string', 'in:all,today,overdue,upcoming,completed'],
            'sort' => ['nullable', 'string', 'max:30'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $sort = in_array($validated['sort'] ?? '', Task::SORTABLE, true)
            ? $validated['sort']
            : 'created_at';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $user = $request->user();

        $query = Task::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'creator:id,name'])
            ->search($validated['search'] ?? null)
            ->status($validated['status'] ?? null)
            ->priority($validated['priority'] ?? null)
            ->assignedTo($validated['assigned_to'] ?? null)
            ->relatedType($validated['related_type'] ?? null)
            ->due($validated['due'] ?? null);

        // Prioridad con orden semántico (CASE portable PostgreSQL/SQLite).
        if ($sort === 'priority') {
            $query->orderByRaw(
                "CASE tasks.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END {$direction}"
            );
        } else {
            $query->orderBy($sort === 'title' ? 'tasks.title' : "tasks.{$sort}", $direction);
        }

        $tasks = $query->paginate(15)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'priority' => $validated['priority'] ?? '',
                'assigned_to' => $validated['assigned_to'] ?? '',
                'related_type' => $validated['related_type'] ?? '',
                'due' => $validated['due'] ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
            'relatedTypes' => RelatedEntity::keys(),
            'users' => DataScope::filterableUsers($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.create', array_merge(
            $this->formData(),
            ['preselected' => $this->parseRelated($request->user(), $request->query('related'))]
        ));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->assertRelatedVisible($request->user(), $data);
        DataScope::assertCanAssignUser($request->user(), $data['assigned_to'] ?? null);

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'due_at' => $data['due_at'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
            ...$this->morphAttributes($data),
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Tarea creada correctamente.');
    }

    public function show(Task $task): View
    {
        $this->authorize('view', $task);

        $viewer = request()->user();

        $task->load(['assignee:id,name,email', 'creator:id,name', 'taskable']);

        return view('tasks.show', [
            'task' => $task,
            'canUpdate' => $viewer->can('update', $task),
            'canViewRelated' => DataScope::canViewModel($viewer, $task->taskable),
        ]);
    }

    public function edit(Task $task): View
    {
        $this->authorize('update', $task);

        $task->load('taskable');

        return view('tasks.edit', array_merge(
            $this->formData($task),
            [
                'task' => $task,
                'currentRelated' => [
                    'type' => RelatedEntity::keyForModel($task->taskable),
                    'id' => $task->taskable?->id,
                ],
            ]
        ));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();

        $this->assertRelatedVisible($request->user(), $data);
        DataScope::assertCanAssignUser($request->user(), $data['assigned_to'] ?? null, $task->assigned_to);

        // Invariantes de estado/fechas, igual que en complete/reopen/cancel.
        $completedAt = $task->completed_at;
        if ($data['status'] === 'completed' && ! $completedAt) {
            $completedAt = now();
        } elseif ($data['status'] !== 'completed') {
            $completedAt = null;
        }

        $task->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'due_at' => $data['due_at'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'completed_at' => $completedAt,
            ...$this->morphAttributes($data),
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Tarea actualizada correctamente.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')
            ->with('success', 'Tarea eliminada correctamente.');
    }

    /**
     * Convierte related_type/related_id validados en columnas polimórficas.
     * Clave inexistente o registro ausente → 422/404 (nunca clases arbitrarias).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function morphAttributes(array $data): array
    {
        if (empty($data['related_type'])) {
            return ['taskable_type' => null, 'taskable_id' => null];
        }

        $related = RelatedEntity::findOrFail($data['related_type'], $data['related_id']);

        return ['taskable_type' => $related::class, 'taskable_id' => $related->id];
    }

    /**
     * Lee ?related=company:3 con whitelist (contextual desde fichas).
     *
     * @return array{type: ?string, id: ?int, label: ?string}
     */
    private function parseRelated(User $actor, ?string $raw): array
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

        if (! DataScope::canViewModel($actor, $model)) {
            return ['type' => null, 'id' => null, 'label' => null];
        }

        return [
            'type' => $model ? $type : null,
            'id' => $model ? (int) $id : null,
            'label' => $model ? RelatedEntity::label($model) : null,
        ];
    }

    /**
     * La entidad relacionada debe ser visible para el autor (sin oráculos IDOR).
     *
     * @param  array<string, mixed>  $data
     */
    private function assertRelatedVisible(User $actor, array $data): void
    {
        if (empty($data['related_type'])) {
            return;
        }

        $related = RelatedEntity::findOrFail($data['related_type'], $data['related_id']);

        abort_unless(DataScope::canViewModel($actor, $related), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Task $task = null): array
    {
        return [
            'assignees' => DataScope::filterableUsers(auth()->user(), $task?->assigned_to),
            'statuses' => $task ? Task::STATUSES : ['pending', 'in_progress'],
            'priorities' => Task::PRIORITIES,
            'relatedTypes' => RelatedEntity::keys(),
        ];
    }
}
