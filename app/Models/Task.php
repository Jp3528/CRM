<?php

namespace App\Models;

use App\Support\DataScope;
use App\Support\RelatedEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public const OPEN_STATUSES = ['pending', 'in_progress'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const SORTABLE = ['title', 'due_at', 'priority', 'status', 'created_at'];

    protected $fillable = [
        'title', 'description', 'status', 'priority',
        'due_at', 'completed_at', 'assigned_to', 'created_by',
        'taskable_type', 'taskable_id',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function getRelatedLabelAttribute(): string
    {
        return RelatedEntity::label($this->taskable);
    }

    public function getRelatedUrlAttribute(): ?string
    {
        $route = RelatedEntity::showRoute($this->taskable);

        return $route && $this->taskable ? route($route, $this->taskable) : null;
    }

    /** @param  Builder<Task>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(tasks.title) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(tasks.description) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  Builder<Task>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('tasks.status', $status);
    }

    /** @param  Builder<Task>  $query */
    public function scopePriority($query, ?string $priority)
    {
        if (blank($priority)) {
            return $query;
        }

        return $query->where('tasks.priority', $priority);
    }

    /** @param  Builder<Task>  $query */
    public function scopeAssignedTo($query, mixed $userId)
    {
        if (blank($userId)) {
            return $query;
        }

        return $query->where('tasks.assigned_to', $userId);
    }

    /**
     * Alcance de datos (Fase 9.5): asignadas o creadas por el usuario/equipo.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeTasks($query, $user);
    }

    /** @param  Builder<Task>  $query */
    public function scopeRelatedType($query, ?string $key)
    {
        if (blank($key)) {
            return $query;
        }

        if ($key === 'none') {
            return $query->whereNull('tasks.taskable_type');
        }

        $class = RelatedEntity::classFor($key);

        return $class ? $query->where('tasks.taskable_type', $class) : $query;
    }

    /** @param  Builder<Task>  $query */
    public function scopeDue($query, ?string $preset)
    {
        return match ($preset) {
            'today' => $query->whereDate('tasks.due_at', today()),
            'overdue' => $query->where('tasks.due_at', '<', now())
                ->whereNotIn('tasks.status', ['completed', 'cancelled']),
            'upcoming' => $query->whereDate('tasks.due_at', '>', today())
                ->whereNotIn('tasks.status', ['completed', 'cancelled']),
            'completed' => $query->where('tasks.status', 'completed'),
            default => $query,
        };
    }
}
