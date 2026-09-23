<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
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

        $query = Task::query()
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
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.create', array_merge(
            $this->formData(),
            ['preselected' => $this->parseRelated($request->query('related'))]
        ));
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

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

        $task->load(['assignee:id,name,email', 'creator:id,name', 'taskable']);

        return view('tasks.show', [
            'task' => $task,
            'canUpdate' => request()->user()->can('update', $task),
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

    /**
     * @return array<string, mixed>
     */
    private function formData(?Task $task = null): array
    {
        $assignedQuery = User::where('status', 'active')->orderBy('name');
        if ($task?->assigned_to) {
            $assignedQuery = User::where(
                fn ($q) => $q->where('status', 'active')->orWhere('id', $task->assigned_to)
            )->orderBy('name');
        }

        return [
            'assignees' => $assignedQuery->get(['id', 'name']),
            'statuses' => $task ? Task::STATUSES : ['pending', 'in_progress'],
            'priorities' => Task::PRIORITIES,
            'relatedTypes' => RelatedEntity::keys(),
        ];
    }
}
