<?php

namespace App\Models;

use App\Support\DataScope;
use Database\Factories\AutomationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Automation extends Model
{
    /** @use HasFactory<AutomationFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'active', 'paused'];

    public const SORTABLE = ['name', 'status', 'trigger_type', 'last_run_at', 'created_at', 'updated_at'];

    protected $fillable = [
        'name', 'description', 'status', 'trigger_type',
        'conditions', 'actions', 'owner_id', 'created_by', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AutomationRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class)->latest();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'paused'], true);
    }

    /** @param  Builder<Automation>  $query */
    public function scopeSearch($query, ?string $term)
    {
        if (blank($term)) {
            return $query;
        }

        $term = mb_strtolower(trim($term));

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(automations.name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(automations.description) LIKE ?', ["%{$term}%"]);
        });
    }

    /** @param  Builder<Automation>  $query */
    public function scopeStatus($query, ?string $status)
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('automations.status', $status);
    }

    /** @param  Builder<Automation>  $query */
    public function scopeTrigger($query, ?string $trigger)
    {
        if (blank($trigger)) {
            return $query;
        }

        return $query->where('automations.trigger_type', $trigger);
    }

    /** @param  Builder<Automation>  $query */
    public function scopeOwnedBy($query, mixed $ownerId)
    {
        if (blank($ownerId)) {
            return $query;
        }

        return $query->where('automations.owner_id', $ownerId);
    }

    /** @param  Builder<Automation>  $query */
    public function scopeVisibleTo($query, User $user)
    {
        return DataScope::scopeOwned($query, $user);
    }
}
