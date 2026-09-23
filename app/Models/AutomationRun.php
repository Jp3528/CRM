<?php

namespace App\Models;

use Database\Factories\AutomationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory;

    public const STATUSES = ['running', 'success', 'skipped', 'failed'];

    protected $fillable = [
        'automation_id', 'event_uuid', 'trigger_type',
        'subject_type', 'subject_id', 'status', 'triggered_by',
        'context', 'result', 'error_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Automation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function triggerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function getDurationSecondsAttribute(): ?float
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return $this->started_at->floatDiffInSeconds($this->finished_at);
    }
}
