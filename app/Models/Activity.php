<?php

namespace App\Models;

use App\Support\RelatedEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    /** Tipos creables manualmente desde la UI. */
    public const MANUAL_TYPES = ['call', 'email', 'meeting', 'note'];

    /** Tipos reservados del sistema (trazabilidad, no editables desde UI). */
    public const SYSTEM_TYPES = ['status_change'];

    public const STATUSES = ['pending', 'completed'];

    public const SORTABLE = ['scheduled_at', 'created_at', 'type', 'status'];

    protected $fillable = [
        'type', 'subject', 'description', 'status',
        'scheduled_at', 'completed_at', 'user_id',
        'subjectable_type', 'subjectable_id',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subjectable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getIsSystemAttribute(): bool
    {
        return in_array($this->type, self::SYSTEM_TYPES, true);
    }

    public function getRelatedLabelAttribute(): string
    {
        return RelatedEntity::label($this->subjectable);
    }

    public function getRelatedUrlAttribute(): ?string
    {
        $route = RelatedEntity::showRoute($this->subjectable);

        return $route && $this->subjectable ? route($route, $this->subjectable) : null;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(activities.subject) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(activities.description) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query */
    public function scopeType($query, ?string $type)
    {
        if (blank($type)) {
            return $query;
        }

        return $query->where('activities.type', $type);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query */
    public function scopeForUser($query, mixed $userId)
    {
        if (blank($userId)) {
            return $query;
        }

        return $query->where('activities.user_id', $userId);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query */
    public function scopeRelatedType($query, ?string $key)
    {
        if (blank($key)) {
            return $query;
        }

        if ($key === 'none') {
            return $query->whereNull('activities.subjectable_type');
        }

        $class = RelatedEntity::classFor($key);

        return $class ? $query->where('activities.subjectable_type', $class) : $query;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Activity>  $query */
    public function scopeScheduledBetween($query, mixed $from, mixed $to)
    {
        if (! blank($from)) {
            $query->whereDate('activities.scheduled_at', '>=', $from);
        }

        if (! blank($to)) {
            $query->whereDate('activities.scheduled_at', '<=', $to);
        }

        return $query;
    }
}
